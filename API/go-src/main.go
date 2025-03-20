package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"
	"time"

	"github.com/gin-contrib/cors"
	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
	"github.com/joho/godotenv"
	log "github.com/sirupsen/logrus"
)

var db *sql.DB
var timezone *time.Location

type Product struct {
	Name        sql.NullString  `json:"name"`
	Description sql.NullString  `json:"description"`
	Price       float64         `json:"price"`
	Stock       int             `json:"stock"`
	Image       sql.NullString  `json:"image"`
	Allergens   json.RawMessage `json:"allergens"`
	CreatedAt   time.Time       `json:"created_at"`
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
	Image     string          `json:"image"`
	Options   json.RawMessage `json:"options"`
	CreatedAt time.Time       `json:"created_at"`
}

func init() {
	log.SetFormatter(&log.TextFormatter{})
	file, err := os.OpenFile("app.log", os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0666)
	if err == nil {
		log.SetOutput(file)
	}
	log.SetLevel(log.InfoLevel)

	err = godotenv.Load()
	if err != nil {
		log.Fatal("Error loading .env file")
		os.Exit(1)
	}

	timezone, err = time.LoadLocation(os.Getenv("APP_TIMEZONE"))
	if err != nil {
		log.Warn("Warning default timezone is UTC")
		timezone = time.UTC
	}

	dbHost := os.Getenv("DB_HOST")
	dbUsername := os.Getenv("DB_USERNAME")
	dbPassword := os.Getenv("DB_PASSWORD")
	dbName := os.Getenv("DB_DATABASE")
	dbPort := os.Getenv("DB_PORT")
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true", dbUsername, dbPassword, dbHost, dbPort, dbName)

	db, err = sql.Open("mysql", dsn)
	if err != nil {
		log.Fatal("Failed to connect to database: ", err)
	}
	db.SetConnMaxLifetime(time.Minute * 3)
	db.SetMaxOpenConns(10)
	db.SetMaxIdleConns(10)
	if err = db.Ping(); err != nil {
		log.Fatal("Failed to ping database: ", err)
	}
}

func main() {
	r := gin.Default()
	r.Use(cors.New(cors.Config{
		AllowOrigins:     []string{os.Getenv("APP_URL")},
		AllowMethods:     []string{"GET"},
		AllowHeaders:     []string{"Origin", "Content-Type", "Accept", "Authorization"},
		ExposeHeaders:    []string{"Content-Length", "Authorization"},
		AllowCredentials: true,
		MaxAge:           12 * time.Hour,
	}))
	go deleteExpiredTokens()
	api := r.Group("/api")
	{
		api.GET("/products/stream", verifyToken(), streamProducts)
		api.GET("/orders/stream", verifyToken(), streamOrders)
	}
	r.Run(":8000")
}

func deleteExpiredTokens() {
	for {
		expiryTime := time.Now().In(timezone).Add(-5 * time.Minute)
		query := "DELETE FROM access_tokens WHERE created_at < ?"
		result, err := db.Exec(query, expiryTime)
		if err != nil {
			log.Errorf("Database error in deleteExpiredTokens: %v", err)
		} else {
			rowsAffected, _ := result.RowsAffected()
			if rowsAffected > 0 {
				log.Infof("Deleted %d expired tokens", rowsAffected)
			}
		}
		time.Sleep(5 * time.Minute)
	}
}

func verifyToken() gin.HandlerFunc {
	return func(c *gin.Context) {
		auth := c.GetHeader("Authorization")
		if auth == "" {
			c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{"detail": "Authorization header missing"})
			return
		}
		token := auth
		if strings.HasPrefix(auth, "Bearer ") {
			token = strings.TrimPrefix(auth, "Bearer ")
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

func getProducts() ([]Product, error) {
	products := []Product{}
	query := "SELECT name, description, price, stock, image, allergens, created_at FROM products"
	rows, err := db.Query(query)
	if err != nil {
		log.Errorf("Database error in getProducts: %v", err)
		return products, err
	}
	defer rows.Close()

	for rows.Next() {
		var p Product
		var allergensStr sql.NullString
		err := rows.Scan(&p.Name, &p.Description, &p.Price, &p.Stock, &p.Image, &allergensStr, &p.CreatedAt)
		if err != nil {
			log.Errorf("Error scanning product row: %v", err)
			continue
		}

		if allergensStr.Valid {
			p.Allergens = json.RawMessage(allergensStr.String)
		}
		products = append(products, p)
	}

	return products, nil
}

func getOrders() ([]Order, error) {
	orders := []Order{}
	query := "SELECT uuid, product_id, quantity, image, options, created_at FROM orders"
	rows, err := db.Query(query)
	if err != nil {
		log.Errorf("Database error in getOrders: %v", err)
		return orders, err
	}
	defer rows.Close()

	for rows.Next() {
		var o Order
		var optionsStr sql.NullString
		err := rows.Scan(&o.UUID, &o.ProductID, &o.Quantity, &o.Image, &optionsStr, &o.CreatedAt)
		if err != nil {
			log.Errorf("Error scanning order row: %v", err)
			continue
		}

		if optionsStr.Valid {
			o.Options = json.RawMessage(optionsStr.String)
		}
		orders = append(orders, o)
	}

	return orders, nil
}

func streamProducts(c *gin.Context) {
	c.Writer.Header().Set("Content-Type", "text/event-stream")
	c.Writer.Header().Set("Cache-Control", "no-cache")
	c.Writer.Header().Set("Connection", "keep-alive")
	c.Writer.Flush()

	ctx, cancel := context.WithTimeout(c.Request.Context(), 5*time.Minute)
	defer cancel()

	startTime := time.Now()
	maxDuration := 5 * time.Minute
	disconnectWarningTime := 4 * time.Minute

	ticker := time.NewTicker(3 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			elapsedTime := time.Since(startTime)
			if elapsedTime >= maxDuration {
				data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connection closed after %v seconds", maxDuration.Seconds())})
				fmt.Fprintf(c.Writer, "event: close\ndata: %s\n\n", data)
				c.Writer.Flush()
				return
			}

			if elapsedTime >= disconnectWarningTime && elapsedTime < maxDuration {
				remaining := int(maxDuration.Seconds() - elapsedTime.Seconds())
				data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connection will close in %d seconds", remaining)})
				fmt.Fprintf(c.Writer, "event: disconnect_warning\ndata: %s\n\n", data)
				c.Writer.Flush()
			}

			products, err := getProducts()
			if err != nil {
				continue
			}

			data, err := json.Marshal(products)
			if err != nil {
				log.Errorf("Error marshaling products: %v", err)
				continue
			}

			fmt.Fprintf(c.Writer, "event: products\ndata: %s\n\n", data)
			c.Writer.Flush()

		case <-ctx.Done():
			return
		}
	}
}

func streamOrders(c *gin.Context) {
	c.Writer.Header().Set("Content-Type", "text/event-stream")
	c.Writer.Header().Set("Cache-Control", "no-cache")
	c.Writer.Header().Set("Connection", "keep-alive")
	c.Writer.Flush()

	ctx, cancel := context.WithTimeout(c.Request.Context(), 5*time.Minute)
	defer cancel()

	startTime := time.Now()
	maxDuration := 5 * time.Minute
	disconnectWarningTime := 4 * time.Minute

	ticker := time.NewTicker(5 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			elapsedTime := time.Since(startTime)
			if elapsedTime >= maxDuration {
				data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connection closed after %v seconds", maxDuration.Seconds())})
				fmt.Fprintf(c.Writer, "event: close\ndata: %s\n\n", data)
				c.Writer.Flush()
				return
			}

			if elapsedTime >= disconnectWarningTime && elapsedTime < maxDuration {
				remaining := int(maxDuration.Seconds() - elapsedTime.Seconds())
				data, _ := json.Marshal(gin.H{"message": fmt.Sprintf("Connection will close in %d seconds", remaining)})
				fmt.Fprintf(c.Writer, "event: disconnect_warning\ndata: %s\n\n", data)
				c.Writer.Flush()
			}

			orders, err := getOrders()
			if err != nil {
				continue
			}

			data, err := json.Marshal(orders)
			if err != nil {
				log.Errorf("Error marshaling orders: %v", err)
				continue
			}

			fmt.Fprintf(c.Writer, "event: orders\ndata: %s\n\n", data)
			c.Writer.Flush()
		case <-ctx.Done():
			return
		}
	}
}
