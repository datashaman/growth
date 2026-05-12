<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Authorize Application - {{ config('app.name', 'MCP Server') }}</title>
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
                width: min(100% - 32px, 440px);
                padding: 32px;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
                box-sizing: border-box;
            }

            .icon {
                display: grid;
                place-items: center;
                width: 48px;
                height: 48px;
                margin: 0 auto 18px;
                color: #2563eb;
            }

            h1 {
                margin: 0;
                font-size: 24px;
                line-height: 1.2;
                text-align: center;
            }

            .intro {
                margin: 8px 0 24px;
                color: #6b7280;
                line-height: 1.5;
                text-align: center;
            }

            .panel {
                padding: 16px;
                margin-bottom: 20px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                background: #f9fafb;
            }

            .label {
                margin: 0 0 6px;
                color: #6b7280;
                font-size: 14px;
            }

            .value {
                margin: 0;
                font-weight: 700;
                overflow-wrap: anywhere;
            }

            .permissions {
                margin: 0 0 24px;
            }

            .permissions-title {
                margin: 0 0 10px;
                font-size: 14px;
                font-weight: 700;
            }

            ul {
                margin: 0;
                padding: 0;
                list-style: none;
            }

            li {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.5;
            }

            .dot {
                width: 8px;
                height: 8px;
                margin-top: 7px;
                border-radius: 999px;
                background: #2563eb;
                flex: 0 0 auto;
            }

            .actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            button {
                width: 100%;
                min-height: 42px;
                padding: 10px 14px;
                border-radius: 6px;
                font: inherit;
                font-weight: 700;
                cursor: pointer;
            }

            .cancel {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #374151;
            }

            .approve {
                border: 1px solid #111827;
                background: #111827;
                color: #ffffff;
            }

            button:focus {
                outline: 2px solid #2563eb;
                outline-offset: 2px;
            }
        </style>
    </head>
    <body>
        <main>
            <div class="icon" aria-hidden="true">
                <svg width="48" height="48" stroke="currentColor" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M20.618 5.984A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>

            <h1>Authorize {{ $client->name }}</h1>
            <p class="intro">This application is requesting access to use available MCP functionality.</p>

            <section class="panel" aria-label="Signed in account">
                <p class="label">Logged in as</p>
                <p class="value">{{ $user->email }}</p>
            </section>

            @if(count($scopes) > 0)
                <section class="permissions" aria-label="Requested permissions">
                    <p class="permissions-title">Permissions</p>

                    <ul>
                        @foreach($scopes as $scope)
                            <li>
                                <span class="dot"></span>
                                <span>{{ $scope->description }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="actions">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="{{ $request->input('state') }}">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="cancel">Cancel</button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="state" value="{{ $request->input('state') }}">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="approve">Authorize</button>
                </form>
            </div>
        </main>
    </body>
</html>
