@push('styles')
<style>
    body {
        background-color: #f9fafb;
        font-family: 'Arial', sans-serif;
    }
    
    .form-container {
        max-width: 400px;
        padding: 24px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: 1px solid #d1d5db;
        text-align: center;
    }
    
    .form-container h1 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: #1f2937;
    }
    
    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }
    
    label {
        font-size: 0.95rem;
        color: #374151;
        font-weight: 600;
    }
    
    label .required {
        color: #dc2626;
        font-weight: bold;
    }
    
    input[type="text"], input[type="password"] {
        width: 100%;
        padding: 12px;
        margin-top: 4px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background-color: #ffffff;
        font-size: 1rem;
        color: #374151;
    }
    
    input[type="text"]:focus, input[type="password"]:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }
    
    button {
        width: 100%;
        padding: 12px;
        background-color: #ea580c;
        color: #fff;
        font-weight: bold;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        transition: background-color 0.3s ease;
    }
    
    button:hover {
        background-color: #d97706;
    }
    
    .remember-group {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        font-size: 0.95rem;
        color: #374151;
    }
    
    .remember-group input {
        margin-right: 8px;
        width: 18px;
        height: 18px;
        accent-color: #f97316;
    }
    
    .remember-group input[type="checkbox"]:checked {
        background-color: #f97316;
        border-color: #f97316;
    }
    
    .dark .form-container {
        background-color: #2f2d2d;
        border-color: #374151;
    }
    
    .dark h1 {
        color: #f9fafb;
    }
    
    .dark label, .dark .remember-group {
        color: #d1d5db;
    }
    
    .dark label .required {
        color: #f87171;
    }
    
    .dark input[type="text"], .dark input[type="password"] {
        background-color: #374151;
        color: #f3f4f6;
        border-color: #4b5563;
    }
    
    .dark input[type="text"]:focus, .dark input[type="password"]:focus {
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.3);
    }
    
    .dark button {
        background-color: #f97316;
    }
    
    .dark button:hover {
        background-color: #ea580c;
    }
    
    .dark .remember-group input[type="checkbox"] {
        background-color: #374151;
        border-color: #4b5563;
    }
    
    .error {
        font-size: 0.875rem;
        color: #dc2626;
        margin-top: 0.25rem;
    }
</style>
@endpush

<div id="login-component">
    <div class="form-container bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200">
        <h1 class="mb-6 text-2xl font-bold">ログイン</h1>
        <form wire:submit.prevent="authenticate">
            <div class="form-group">
                <label for="name" class="text-gray-900 dark:text-gray-200">
                    ユーザー名<span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    wire:model.lazy="name"
                    placeholder="ユーザー名を入力してください"
                    class="bg-gray-50 dark:bg-gray-600 dark:text-white">
                @error('name')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="dark:text-gray-200">
                    パスワード<span class="required">*</span>
                </label>
                <input 
                    type="password"
                    id="password"
                    wire:model.lazy="password"
                    placeholder="パスワードを入力してください"
                    class="bg-gray-50 dark:bg-gray-600 dark:text-white">
            </div>

            <div class="remember-group">
                <input 
                    id="remember"
                    type="checkbox"
                    wire:model.lazy="remember"
                    class="rounded border-gray-300 shadow-sm focus:ring focus:ring-orange-500">
                <label for="remember">Remember me</label>
            </div>

            @if(config('services.turnstile.secret') && config('services.turnstile.sitekey'))
                <div x-data="turnstileHandler()" x-init="initTurnstile()">
                    <div class="form-group">
                        <div class="cf-turnstile-wrapper" wire:ignore>
                            <div id="cf-turnstile-widget"
                                 x-bind:data-theme="document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                                 class="cf-turnstile" 
                                 data-sitekey="{{ config('services.turnstile.sitekey') }}" 
                                 data-callback="onTurnstileSuccess" 
                                 data-error-callback="onTurnstileError">
                            </div>
                        </div>
                        <input type="hidden" wire:model="turnstileToken">
                        @error('turnstileToken')
                            <p class="error">{{ $errors->first('turnstileToken') }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            <button type="submit">ログイン</button>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script>
    function turnstileHandler() {
        return {
            turnstileResetTimeout: null,
            initTurnstile() {
                window.onTurnstileSuccess = (token) => {
                    @this.set('turnstileToken', token);
                    this.scheduleTurnstileReset();
                };
                window.onTurnstileError = (error) => {
                    console.log("Turnstile error:", error);
                    @this.set('turnstileToken', '');
                    if (error === "timeout-or-duplicate" && typeof turnstile !== "undefined" && typeof turnstile.reset === "function") {
                        turnstile.reset("cf-turnstile-widget");
                    }
                    alert('Turnstile の検証に失敗しました。');
                };
            },
            scheduleTurnstileReset() {
                if (this.turnstileResetTimeout) {
                    clearTimeout(this.turnstileResetTimeout);
                }
                this.turnstileResetTimeout = setTimeout(() => {
                    @this.set('turnstileToken', '');
                    if (typeof turnstile !== "undefined" && typeof turnstile.reset === "function") {
                        turnstile.reset("cf-turnstile-widget");
                    }
                    alert('Turnstile トークンが期限切れです。再認証してください。');
                }, 120000);
            }
        }
    }
</script>
@endpush
