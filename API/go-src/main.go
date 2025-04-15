package main

import (
	"bytes"
	"compress/gzip"
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/gin-contrib/cors"
	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"github.com/goccy/go-yaml"
	"github.com/google/uuid"
	log "github.com/sirupsen/logrus"
	_ "modernc.org/sqlite"
)

var db *sql.DB
var timezone *time.Location
var config Config

type EventManager struct {
	productSubscribers map[string]chan []Product
	orderSubscribers   map[string]chan []Order
	mu                 sync.RWMutex
	bufferSize         int
	maxBufferSize      int
}

var eventManager = &EventManager{
	productSubscribers: make(map[string]chan []Product),
	orderSubscribers:   make(map[string]chan []Order),
	bufferSize:         100,
	maxBufferSize: func() int {
		if config.MaxBufferSize > 0 {
			return config.MaxBufferSize
		}
		return 500
	}(),
}

func (em *EventManager) increaseBufferSize() bool {
	em.mu.Lock()
	defer em.mu.Unlock()

	if em.bufferSize < em.maxBufferSize {
		oldSize := em.bufferSize
		newSize := min(em.bufferSize*2, em.maxBufferSize)
		em.bufferSize = newSize
		log.Warnf("Buffer size increased from %d to %d (max: %d)", oldSize, newSize, em.maxBufferSize)
		return true
	}
	log.Warnf("Cannot increase buffer size: already at maximum (%d)", em.maxBufferSize)
	return false
}

func tryPublishWithRetry[T any](em *EventManager, ch chan []T, items []T) bool {
	for attempts := 0; attempts < 2; attempts++ {
		select {
		case ch <- items:
			if attempts > 0 {
				log.Infof("Successfully delivered updates after buffer resize")
			}
			return true
		default:
			if attempts == 0 && em.increaseBufferSize() {
				log.Warnf("Attempting to resize buffer and retry delivery (current channel capacity: %d)", cap(ch))
				newCh := make(chan []T, em.bufferSize)
				close(ch)
				copied := 0
				for item := range ch {
					newCh <- item
					copied++
				}
				log.Warnf("Copied %d existing messages to new channel (new capacity: %d)", copied, cap(newCh))
				ch = newCh
				continue
			}
			log.Warnf("Updates could not be delivered: buffer full (capacity: %d) and max size reached", cap(ch))
			return false
		}
	}
	return false
}

func (em *EventManager) SubscribeProducts(id string) chan []Product {
	em.mu.Lock()
	defer em.mu.Unlock()

	ch := make(chan []Product, em.bufferSize)
	em.productSubscribers[id] = ch
	return ch
}

func (em *EventManager) SubscribeOrders(id string) chan []Order {
	em.mu.Lock()
	defer em.mu.Unlock()

	ch := make(chan []Order, em.bufferSize)
	em.orderSubscribers[id] = ch
	return ch
}

func (em *EventManager) UnsubscribeProducts(id string) {
	em.mu.Lock()
	defer em.mu.Unlock()

	if ch, exists := em.productSubscribers[id]; exists {
		close(ch)
		delete(em.productSubscribers, id)
	}
}

func (em *EventManager) UnsubscribeOrders(id string) {
	em.mu.Lock()
	defer em.mu.Unlock()

	if ch, exists := em.orderSubscribers[id]; exists {
		close(ch)
		delete(em.orderSubscribers, id)
	}
}

func (em *EventManager) PublishProducts(products []Product) {
	em.mu.RLock()
	defer em.mu.RUnlock()

	for id, ch := range em.productSubscribers {
		if !tryPublishWithRetry(em, ch, products) {
			log.Errorf("Failed to deliver product updates to subscriber %s", id)
		}
	}
}

func (em *EventManager) PublishOrders(orders []Order) {
	em.mu.RLock()
	defer em.mu.RUnlock()

	for id, ch := range em.orderSubscribers {
		if !tryPublishWithRetry(em, ch, orders) {
			log.Errorf("Failed to deliver order updates to subscriber %s", id)
		}
	}
}

type DataFetcher[T any] struct {
	getAll       func() ([]T, error)
	getSince     func(time.Time) ([]T, error)
	publish      func([]T)
	tableName    string
	pollInterval int
}

func startEventPublisher[T any](fetcher DataFetcher[T]) {
	ticker := time.NewTicker(time.Duration(fetcher.pollInterval) * time.Second)
	defer ticker.Stop()

	initialData, err := fetcher.getAll()
	if err != nil {
		log.Errorf("Error fetching initial %s: %v", fetcher.tableName, err)
	} else {
		fetcher.publish(initialData)
	}

	lastUpdate, err := getLastUpdateTime(fetcher.tableName)
	if err != nil {
		log.Errorf("Error getting last %s update time: %v", fetcher.tableName, err)
		lastUpdate = time.Now().Add(-1 * time.Hour)
	}

	for range ticker.C {
		updatedData, err := fetcher.getSince(lastUpdate)
		if err != nil {
			log.Errorf("Error fetching updated %s: %v", fetcher.tableName, err)
			continue
		}

		if len(updatedData) > 0 {
			allData, err := fetcher.getAll()
			if err != nil {
				log.Errorf("Error fetching all %s: %v", fetcher.tableName, err)
				continue
			}

			fetcher.publish(allData)
			lastUpdate = time.Now()
		}
	}
}

func startEventPublishers() {
	productInterval := 5
	orderInterval := 3

	if config.ProductPollInterval > 0 {
		productInterval = config.ProductPollInterval
	}
	if config.OrderPollInterval > 0 {
		orderInterval = config.OrderPollInterval
	}

	productFetcher := DataFetcher[Product]{
		getAll:       getProducts,
		getSince:     getProductsSince,
		publish:      eventManager.PublishProducts,
		tableName:    "products",
		pollInterval: productInterval,
	}

	orderFetcher := DataFetcher[Order]{
		getAll:       getOrders,
		getSince:     getOrdersSince,
		publish:      eventManager.PublishOrders,
		tableName:    "orders",
		pollInterval: orderInterval,
	}

	go startEventPublisher(productFetcher)
	go startEventPublisher(orderFetcher)
}

type Config struct {
	Debug               bool   `yaml:"DEBUG"`
	DBConnection        string `yaml:"DB_CONNECTION"`
	DBHost              string `yaml:"DB_HOST"`
	DBPort              string `yaml:"DB_PORT"`
	DBDatabase          string `yaml:"DB_DATABASE"`
	DBUsername          string `yaml:"DB_USERNAME"`
	DBPassword          string `yaml:"DB_PASSWORD"`
	AppTimezone         string `yaml:"APP_TIMEZONE"`
	AppUrl              string `yaml:"APP_URL"`
	AppPort             string `yaml:"APP_PORT"`
	ProductPollInterval int    `yaml:"PRODUCT_POLL_INTERVAL"`
	OrderPollInterval   int    `yaml:"ORDER_POLL_INTERVAL"`
	MaxBufferSize       int    `yaml:"MAX_BUFFER_SIZE"`
}

type Product struct {
	Name        sql.NullString  `json:"name"`
	Description sql.NullString  `json:"description"`
	Price       float64         `json:"price"`
	Stock       int             `json:"stock"`
	Image       sql.NullString  `json:"image"`
	Allergens   json.RawMessage `json:"allergens"`
	CreatedAt   time.Time       `json:"created_at"`
}

func (p Product) Equal(other Product) bool {
	return p.Name.String == other.Name.String &&
		p.Description.String == other.Description.String &&
		p.Price == other.Price &&
		p.Stock == other.Stock &&
		p.Image.String == other.Image.String &&
		bytes.Equal(p.Allergens, other.Allergens) &&
		p.CreatedAt.Equal(other.CreatedAt)
}

func ProductsEqual(a, b []Product) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if !a[i].Equal(b[i]) {
			return false
		}
	}
	return true
}

func (p Product) MarshalJSON() ([]byte, error) {
	return json.Marshal(&struct {
		Name        string          `json:"name"`
		Description string          `json:"description"`
		Price       float64         `json:"price"`
		Stock       int             `json:"stock"`
		Image       string          `json:"image"`
		Allergens   json.RawMessage `json:"allergens"`
		CreatedAt   time.Time       `json:"created_at"`
	}{
		Name:        p.Name.String,
		Description: p.Description.String,
		Price:       p.Price,
		Stock:       p.Stock,
		Image:       p.Image.String,
		Allergens:   p.Allergens,
		CreatedAt:   p.CreatedAt,
	})
}

type Order struct {
	UUID      string          `json:"uuid"`
	ProductID int             `json:"product_id"`
	Quantity  int             `json:"quantity"`
	Image     sql.NullString  `json:"image"`
	Options   json.RawMessage `json:"options"`
	CreatedAt time.Time       `json:"created_at"`
}

func (o Order) Equal(other Order) bool {
	return o.UUID == other.UUID &&
		o.ProductID == other.ProductID &&
		o.Quantity == other.Quantity &&
		o.Image.String == other.Image.String &&
		bytes.Equal(o.Options, other.Options) &&
		o.CreatedAt.Equal(other.CreatedAt)
}

func OrdersEqual(a, b []Order) bool {
	if len(a) != len(b) {
		return false
	}
	for i := range a {
		if !a[i].Equal(b[i]) {
			return false
		}
	}
	return true
}

// init initializes logging, loads configuration from config.yml, sets up the application timezone, and establishes the database connection.
// It configures log output to both stdout and a file, parses the YAML configuration, and defaults to UTC if the APP_TIMEZONE is unset or invalid.
// Depending on the configuration, it connects to either a SQLite or a MySQL database, setting appropriate connection limits and verifying connectivity.
// Fatal errors during these initialization steps result in log termination and application exit.
func init() {
	log.SetFormatter(&log.TextFormatter{})
	file, err := os.OpenFile("app.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err == nil {
		mw := io.MultiWriter(os.Stdout, file)
		log.SetOutput(mw)
	}
	log.SetLevel(log.InfoLevel)

	configData, err := os.ReadFile("config.yml")
	if err != nil {
		log.Fatal("Error loading config.yml file: ", err)
		os.Exit(1)
	}

	if err := yaml.Unmarshal(configData, &config); err != nil {
		log.Fatal("Error parsing config.yml: ", err)
		os.Exit(1)
	}

	if config.AppTimezone == "" {
		log.Warn("Warning: APP_TIMEZONE is not set. Using UTC")
		timezone = time.UTC
	} else {
		timezone, err = time.LoadLocation(config.AppTimezone)
		if err != nil {
			log.Warnf("Warning: timezone '%s' is invalid. Using UTC: %v", config.AppTimezone, err)
			timezone = time.UTC
		}
	}

	dbType := config.DBConnection
	log.Infof("DB_CONNECTION: %s", dbType)

	if dbType == "sqlite" {
		dbPath := config.DBDatabase

		if dbPath == "" {
			homeDir, err := os.UserHomeDir()
			if err != nil {
				log.Fatal("Failed to get user home directory: ", err)
			}
			dbPath = fmt.Sprintf("%s/database/database.sqlite", homeDir)
			log.Infof("Using default Laravel SQLite path: %s", dbPath)
		}

		if !filepath.IsAbs(dbPath) {
			log.Fatalf("SQLite database path must be absolute: %s", dbPath)
		}

		_, err = os.Stat(dbPath)
		if os.IsNotExist(err) {
			dbDir := filepath.Dir(dbPath)
			if err := os.MkdirAll(dbDir, 0755); err != nil {
				log.Fatalf("Failed to create database directory: %v", err)
			}
			log.Warnf("Database file does not exist. A new database will be created: %s", dbPath)
		}

		db, err = sql.Open("sqlite", dbPath)
		if err != nil {
			log.Fatal("Failed to connect to SQLite database: ", err)
		}
	} else {
		dbHost := config.DBHost
		dbUsername := config.DBUsername
		dbPassword := config.DBPassword
		dbName := config.DBDatabase
		dbPort := config.DBPort
		dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true", dbUsername, dbPassword, dbHost, dbPort, dbName)

		db, err = sql.Open("mysql", dsn)
		if err != nil {
			log.Fatal("Failed to connect to MySQL database: ", err)
		}
	}

	db.SetConnMaxLifetime(time.Minute * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)
	if err = db.Ping(); err != nil {
		log.Fatal("Failed to ping database: ", err)
	}
}

// main initializes and starts the web server. It configures the Gin framework based on the debug flag by setting
// the appropriate mode and logging level, applies CORS middleware and trusted proxies, and registers secured API
// routes for streaming product and order data. The server then listens on port 8000.
func main() {
	if config.Debug {
		gin.SetMode(gin.DebugMode)
		log.SetLevel(log.DebugLevel)
	} else {
		gin.SetMode(gin.ReleaseMode)
		log.SetLevel(log.InfoLevel)
	}

	startEventPublishers()

	r := gin.New()
	r.Use(cors.New(cors.Config{
		AllowOrigins:     []string{config.AppUrl},
		AllowMethods:     []string{"GET"},
		AllowHeaders:     []string{"Origin", "Content-Type", "Accept", "Authorization"},
		ExposeHeaders:    []string{"Content-Length", "Authorization"},
		AllowCredentials: true,
		MaxAge:           12 * time.Hour,
	}))
	r.SetTrustedProxies([]string{config.AppUrl})
	api := r.Group("/api")
	{
		api.GET("/products/stream", verifyToken(), streamProducts)
		api.GET("/orders/stream", verifyToken(), streamOrders)
	}
	if err := r.Run(":" + config.AppPort); err != nil {
		log.Fatalf("Failed to start server: %v", err)
	}
}

// VerifyToken returns a Gin middleware that authenticates HTTP requests by validating an access token.
// It retrieves the Authorization header, strips the "Bearer " prefix if present, and checks that the token
// was created within the last 5 minutes by querying the database. If the token is missing, invalid, or expired,
// the middleware aborts the request with an appropriate HTTP status, otherwise it attaches the token to the
// request context and allows the request to proceed.
func verifyToken() gin.HandlerFunc {
	return func(c *gin.Context) {
		log.Debug("Starting: Token verification")
		auth := c.GetHeader("Authorization")
		if auth == "" {
			log.Debug("Authorization header not found")
			c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{"detail": "Authorization header missing"})
			return
		}
		token := auth
		if strings.HasPrefix(auth, "Bearer ") {
			token = strings.TrimPrefix(auth, "Bearer ")
			if config.Debug {
				log.Debugf("Bearer token detected: %s", token)
			}
		}
		validTime := time.Now().In(timezone).Add(-5 * time.Minute)
		query := "SELECT id, access_token, created_at FROM access_tokens WHERE access_token = ? AND created_at >= ?"
		var id int
		var accessToken string
		var createdAt time.Time
		err := db.QueryRow(query, token, validTime).Scan(&id, &accessToken, &createdAt)
		if err != nil {
			if err == sql.ErrNoRows {
				c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{"detail": "Invalid or expired token"})
				return
			}
			log.Errorf("Database error in verifyToken: %v", err)
			c.AbortWithStatusJSON(http.StatusInternalServerError, gin.H{"detail": fmt.Sprintf("Database error: %v", err)})
			return
		}

		c.Set("token", token)
		c.Next()
	}
}

func getProductsSince(since time.Time) ([]Product, error) {
	log.Debug("Starting: Fetching product data since", since)
	products := []Product{}
	stmt, err := db.Prepare("SELECT name, description, price, stock, image, allergens, created_at FROM products WHERE updated_at > ?")
	if err != nil {
		log.Errorf("Error preparing statement: %v", err)
		return products, err
	}
	defer stmt.Close()

	rows, err := stmt.Query(since)
	if err != nil {
		log.Errorf("Database error in getProductsSince: %v", err)
		return products, err
	}
	defer rows.Close()

	count := 0
	for rows.Next() {
		var p Product
		var allergensStr sql.NullString
		err := rows.Scan(&p.Name, &p.Description, &p.Price, &p.Stock, &p.Image, &allergensStr, &p.CreatedAt)
		if err != nil {
			log.Errorf("Error scanning product row: %v", err)
			continue
		}
		count++
		log.Debugf("Product data read #%d: Name: %s, Price: %.2f, Stock: %d", count, p.Name.String, p.Price, p.Stock)

		if allergensStr.Valid {
			p.Allergens = json.RawMessage(allergensStr.String)
		}
		products = append(products, p)
	}

	return products, nil
}

func getOrdersSince(since time.Time) ([]Order, error) {
	log.Debug("Starting: Fetching order data since", since)
	orders := []Order{}
	stmt, err := db.Prepare("SELECT uuid, product_id, quantity, image, options, created_at FROM orders WHERE updated_at > ?")
	if err != nil {
		log.Errorf("Error preparing statement: %v", err)
		return orders, err
	}
	defer stmt.Close()

	rows, err := stmt.Query(since)
	if err != nil {
		log.Errorf("Database error in getOrdersSince: %v", err)
		return orders, err
	}
	defer rows.Close()

	count := 0
	for rows.Next() {
		var o Order
		var optionsStr sql.NullString
		err := rows.Scan(&o.UUID, &o.ProductID, &o.Quantity, &o.Image, &optionsStr, &o.CreatedAt)
		if err != nil {
			log.Errorf("Error scanning order row: %v", err)
			continue
		}
		count++
		log.Debugf("Order data read #%d: UUID: %s, Product ID: %d, Quantity: %d", count, o.UUID, o.ProductID, o.Quantity)

		if optionsStr.Valid {
			o.Options = json.RawMessage(optionsStr.String)
		}
		orders = append(orders, o)
	}

	return orders, nil
}

func getLastUpdateTime(tableName string) (time.Time, error) {
	var lastUpdate time.Time
	validTables := map[string]bool{
		"products": true,
		"orders":   true,
	}
	if !validTables[tableName] {
		return time.Time{}, fmt.Errorf("invalid table name: %s", tableName)
	}
	query := fmt.Sprintf("SELECT MAX(updated_at) FROM %s", tableName)
	err := db.QueryRow(query).Scan(&lastUpdate)
	if err != nil {
		if err == sql.ErrNoRows {
			return time.Time{}, nil
		}
		return time.Time{}, err
	}
	return lastUpdate, nil
}

// getProducts retrieves all product records from the database.
// It executes a SQL query on the products table and scans each row into a Product struct.
// Rows that fail during scanning are logged and skipped, while a query execution error is returned.
func getProducts() ([]Product, error) {
	log.Debug("Starting: Fetching product data")
	products := []Product{}
	stmt, err := db.Prepare("SELECT name, description, price, stock, image, allergens, created_at FROM products")
	if err != nil {
		log.Errorf("Error preparing statement: %v", err)
		return products, err
	}
	defer stmt.Close()

	rows, err := stmt.Query()
	if err != nil {
		log.Errorf("Database error in getProducts: %v", err)
		return products, err
	}
	defer rows.Close()

	count := 0
	for rows.Next() {
		var p Product
		var allergensStr sql.NullString
		err := rows.Scan(&p.Name, &p.Description, &p.Price, &p.Stock, &p.Image, &allergensStr, &p.CreatedAt)
		if err != nil {
			log.Errorf("Error scanning product row: %v", err)
			continue
		}
		count++
		log.Debugf("Product data read #%d: Name: %s, Price: %.2f, Stock: %d", count, p.Name.String, p.Price, p.Stock)

		if allergensStr.Valid {
			p.Allergens = json.RawMessage(allergensStr.String)
		}
		products = append(products, p)
	}

	return products, nil
}

// getOrders retrieves orders from the database and returns them as a slice of Order.
// It executes a SQL query to fetch the uuid, product_id, quantity, image, options, and created_at fields from the "orders" table.
// In case of a query error, the function immediately returns the error. For each fetched row, any scan error is logged and that row is skipped.
// When the options field is valid, it is converted from a SQL null string to a JSON raw message before being assigned.
func getOrders() ([]Order, error) {
	log.Debug("Starting: Fetching order data")
	orders := []Order{}
	stmt, err := db.Prepare("SELECT uuid, product_id, quantity, image, options, created_at FROM orders")
	if err != nil {
		log.Errorf("Error preparing statement: %v", err)
		return orders, err
	}
	defer stmt.Close()

	log.Debug("Executing prepared statement for orders")
	rows, err := stmt.Query()
	if err != nil {
		log.Errorf("Database error in getOrders: %v", err)
		return orders, err
	}
	defer rows.Close()

	count := 0
	for rows.Next() {
		var o Order
		var optionsStr sql.NullString
		err := rows.Scan(&o.UUID, &o.ProductID, &o.Quantity, &o.Image, &optionsStr, &o.CreatedAt)
		if err != nil {
			log.Errorf("Error scanning order row: %v", err)
			continue
		}
		count++
		log.Debugf("Order data read #%d: UUID: %s, Product ID: %d, Quantity: %d", count, o.UUID, o.ProductID, o.Quantity)

		if optionsStr.Valid {
			o.Options = json.RawMessage(optionsStr.String)
		}
		orders = append(orders, o)
	}

	return orders, nil
}

func createStream(c *gin.Context, streamType string, dataFetcher func() (any, error), eventChan chan any) {
	log.Debugf("Starting: %s stream broadcast", streamType)
	acceptEncoding := c.GetHeader("Accept-Encoding")
	useGzip := strings.Contains(acceptEncoding, "gzip")

	cleanup := make(chan struct{})
	defer close(cleanup)

	if useGzip {
		log.Debug("Using Gzip compression")
		c.Writer.Header().Set("Content-Encoding", "gzip")
		gz := gzip.NewWriter(c.Writer)
		defer gz.Close()
		c.Writer = &gzipResponseWriter{Writer: gz, ResponseWriter: c.Writer}
	}

	c.Writer.Header().Set("Content-Type", "text/event-stream")
	c.Writer.Header().Set("Cache-Control", "no-cache, no-transform")
	c.Writer.Header().Set("Connection", "keep-alive")
	c.Writer.Header().Set("X-Accel-Buffering", "no")
	c.Writer.Header().Set("Transfer-Encoding", "chunked")
	c.Writer.Flush()

	data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connected to %s stream", streamType)})
	fmt.Fprintf(c.Writer, "event: connected\ndata: %s\n\n", data)
	c.Writer.Flush()

	ctx, cancel := context.WithTimeout(c.Request.Context(), 5*time.Minute)
	defer cancel()

	initialData, err := dataFetcher()
	if err == nil {
		data, _ := json.Marshal(initialData)
		fmt.Fprintf(c.Writer, "event: %s\ndata: %s\n\n", streamType, data)
		c.Writer.Flush()
	}

	startTime := time.Now()
	maxDuration := 5 * time.Minute
	disconnectWarningTime := 4 * time.Minute
	var warningSentMu sync.Mutex
	warningSent := false

	eventTicker := time.NewTicker(100 * time.Millisecond)
	defer eventTicker.Stop()

	for {
		select {
		case eventData := <-eventChan:
			data, err := json.Marshal(eventData)
			if err != nil {
				log.Errorf("Error marshaling %s data: %v", streamType, err)
				continue
			}
			fmt.Fprintf(c.Writer, "event: %s\ndata: %s\n\n", streamType, data)
			c.Writer.Flush()

		case <-ctx.Done():
			log.Debug("Context cancelled or timeout reached")
			return

		case <-cleanup:
			log.Debug("Cleanup requested")
			return

		case <-eventTicker.C:
			elapsedTime := time.Since(startTime)
			if elapsedTime >= maxDuration {
				data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connection closed after %v seconds", maxDuration.Seconds())})
				fmt.Fprintf(c.Writer, "event: close\ndata: %s\n\n", data)
				c.Writer.Flush()
				return
			}

			warningSentMu.Lock()
			shouldSendWarning := elapsedTime >= disconnectWarningTime && elapsedTime < maxDuration && !warningSent
			if shouldSendWarning {
				warningSent = true
			}
			warningSentMu.Unlock()
			if shouldSendWarning {
				go func() {
					countdown := 60
					ticker := time.NewTicker(1 * time.Second)
					defer ticker.Stop()

					for countdown > 0 {
						select {
						case <-ticker.C:
							data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connection will close in %d seconds", countdown)})
							fmt.Fprintf(c.Writer, "event: disconnect_warning\ndata: %s\n\n", data)
							c.Writer.Flush()
							countdown--
						case <-ctx.Done():
							return
						case <-cleanup:
							return
						}
					}

					data, _ := json.Marshal(gin.H{"message": "Connection closed"})
					fmt.Fprintf(c.Writer, "event: close\ndata: %s\n\n", data)
					c.Writer.Flush()
					cancel()
				}()
			}
		}
	}
}

func streamProducts(c *gin.Context) {
	clientID := uuid.New().String()
	productChan := eventManager.SubscribeProducts(clientID)
	defer func() {
		eventManager.UnsubscribeProducts(clientID)
	}()

	productFetcher := func() (any, error) {
		return getProducts()
	}

	anyChan := make(chan any, eventManager.bufferSize)
	go func() {
		defer close(anyChan)
		for p := range productChan {
			anyChan <- p
		}
	}()

	createStream(c, "products", productFetcher, anyChan)
}

func streamOrders(c *gin.Context) {
	clientID := uuid.New().String()
	orderChan := eventManager.SubscribeOrders(clientID)
	defer func() {
		eventManager.UnsubscribeOrders(clientID)
	}()

	orderFetcher := func() (any, error) {
		return getOrders()
	}

	anyChan := make(chan any, eventManager.bufferSize)
	go func() {
		defer close(anyChan)
		for o := range orderChan {
			anyChan <- o
		}
	}()

	createStream(c, "orders", orderFetcher, anyChan)
}

type gzipResponseWriter struct {
	io.Writer
	gin.ResponseWriter
}

func (w *gzipResponseWriter) Write(b []byte) (int, error) {
	return w.Writer.Write(b)
}

func (w *gzipResponseWriter) WriteString(s string) (int, error) {
	return w.Writer.Write([]byte(s))
}

func (w *gzipResponseWriter) Flush() {
	if f, ok := w.Writer.(*gzip.Writer); ok {
		f.Flush()
	}
	w.ResponseWriter.Flush()
}
