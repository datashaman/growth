@props([
    'value',
    'format' => 'relative',
])

@php
    $date = $value instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($value) : ($value ? \Illuminate\Support\Carbon::parse($value) : null);
    $iso = $date?->toJSON();
    $fallback = match ($format) {
        'date' => $date?->format('Y-m-d'),
        'datetime' => $date?->format('Y-m-d H:i'),
        default => $date?->diffForHumans(),
    };
@endphp

@if ($date)
    <time datetime="{{ $iso }}" title="{{ $iso }}" data-local-time data-format="{{ $format }}">{{ $fallback }}</time>

    @once
        <script>
            (() => {
                const formatRelative = (date) => {
                    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
                    const units = [
                        ['year', 31536000],
                        ['month', 2592000],
                        ['week', 604800],
                        ['day', 86400],
                        ['hour', 3600],
                        ['minute', 60],
                    ];
                    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

                    for (const [unit, size] of units) {
                        if (Math.abs(seconds) >= size || unit === 'minute') {
                            return formatter.format(Math.round(seconds / size), unit);
                        }
                    }
                };

                const formatTime = (element) => {
                    const date = new Date(element.dateTime);

                    if (Number.isNaN(date.getTime())) {
                        return;
                    }

                    const format = element.dataset.format;

                    if (format === 'date') {
                        element.textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
                    } else if (format === 'datetime') {
                        element.textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
                    } else {
                        element.textContent = formatRelative(date);
                    }
                };

                window.GrowthFormatLocalTimes = () => {
                    document.querySelectorAll('[data-local-time]').forEach(formatTime);
                };

                if (! window.GrowthFormatLocalTimesBound) {
                    window.GrowthFormatLocalTimesBound = true;
                    document.addEventListener('DOMContentLoaded', window.GrowthFormatLocalTimes);
                    document.addEventListener('livewire:navigated', window.GrowthFormatLocalTimes);
                    document.addEventListener('livewire:updated', window.GrowthFormatLocalTimes);
                }

                window.GrowthFormatLocalTimes();
            })();
        </script>
    @endonce
@else
    {{ '—' }}
@endif
