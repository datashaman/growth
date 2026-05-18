<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @auth
                <livewire:workspace-switcher />
                <livewire:project-switcher />
                <livewire:lens-switcher />

                {{-- On desktop these live in the top bar; the sidebar keeps them for mobile. --}}
                <div class="grid gap-2 lg:hidden">
                    <livewire:omni-search />
                    <livewire:notification-bell />
                </div>
            @endauth

            @php($lens = auth()->check() ? auth()->user()->lens() : \App\Support\ViewLens::All)

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Project')" class="grid">
                    @if ($lens->reveals('dashboard'))
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('intent'))
                        <flux:sidebar.item icon="megaphone" :href="route('intent')" :current="request()->routeIs('intent')" wire:navigate>
                            {{ __('Intent') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('requirements'))
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('requirements')" :current="request()->routeIs('requirements')" wire:navigate>
                            {{ __('Requirements') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('architecture'))
                        <flux:sidebar.item icon="cube" :href="route('architecture')" :current="request()->routeIs('architecture')" wire:navigate>
                            {{ __('Architecture') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('verification'))
                        <flux:sidebar.item icon="check-badge" :href="route('verification')" :current="request()->routeIs('verification')" wire:navigate>
                            {{ __('Verification') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('plan'))
                        <flux:sidebar.item icon="calendar-days" :href="route('plan')" :current="request()->routeIs('plan')" wire:navigate>
                            {{ __('Plan') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('evidence'))
                        <flux:sidebar.item icon="archive-box" :href="route('evidence')" :current="request()->routeIs('evidence')" wire:navigate>
                            {{ __('Evidence') }}
                        </flux:sidebar.item>
                    @endif
                    @if ($lens->reveals('changes'))
                        <flux:sidebar.item icon="arrows-right-left" :href="route('changes')" :current="request()->routeIs('changes')" wire:navigate>
                            {{ __('Changes') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    <flux:sidebar.item icon="bolt" :href="route('tool-invocations')" :current="request()->routeIs('tool-invocations')" wire:navigate>
                        {{ __('Tool invocations') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('feedback')" :current="request()->routeIs('feedback')" wire:navigate>
                        {{ __('Feedback') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        <!-- Top bar: sidebar toggle (mobile), omni-search + notifications (desktop), user menu -->
        <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            @auth
                <div class="me-2 hidden items-center gap-2 lg:flex">
                    <div class="w-64">
                        <livewire:omni-search />
                    </div>
                    <livewire:notification-bell variant="bar" />
                </div>
            @endauth

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
