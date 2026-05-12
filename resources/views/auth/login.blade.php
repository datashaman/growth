<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sign in - {{ config('app.name', 'Growth') }}</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: #f8fafc;
                color: #111827;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            main {
                width: min(100% - 32px, 420px);
                padding: 32px;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
            }

            h1 {
                margin: 0 0 6px;
                font-size: 24px;
                line-height: 1.2;
            }

            p {
                margin: 0 0 24px;
                color: #6b7280;
            }

            label {
                display: block;
                margin: 16px 0 6px;
                font-size: 14px;
                font-weight: 600;
            }

            input[type="email"],
            input[type="password"] {
                box-sizing: border-box;
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font: inherit;
            }

            input:focus {
                outline: 2px solid #2563eb;
                outline-offset: 1px;
                border-color: #2563eb;
            }

            .row {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 18px 0 24px;
                color: #374151;
                font-size: 14px;
            }

            button {
                width: 100%;
                padding: 11px 14px;
                border: 0;
                border-radius: 6px;
                background: #111827;
                color: #ffffff;
                font: inherit;
                font-weight: 700;
                cursor: pointer;
            }

            .error {
                margin-top: 8px;
                color: #b91c1c;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>Sign in</h1>
            <p>Use your Growth account to approve MCP access.</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label class="row">
                    <input name="remember" type="checkbox" value="1">
                    <span>Keep me signed in</span>
                </label>

                <button type="submit">Sign in</button>
            </form>
        </main>
    </body>
</html>
