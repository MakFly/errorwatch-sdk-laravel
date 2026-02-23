<script>
(function() {
    // ErrorWatch Session Replay Script
    // This script captures user interactions for debugging purposes

    const config = {
        endpoint: '{{ $endpoint ?? "" }}',
        apiKey: '{{ $apiKey ?? "" }}',
        sampleRate: {{ $sampleRate ?? 0.1 }},
        debug: {{ $debug ? 'true' : 'false' }}
    };

    // Check sampling
    if (Math.random() > config.sampleRate) {
        if (config.debug) {
            console.log('[ErrorWatch] Session replay skipped (sampling)');
        }
        return;
    }

    // Simple session replay recording
    const ErrorWatchReplay = {
        events: [],
        startTime: Date.now(),
        maxEvents: 1000,

        init: function() {
            this.recordEvent('init', { url: window.location.href });

            // Record clicks
            document.addEventListener('click', this.handleClick.bind(this), true);

            // Record input changes (debounced)
            let inputTimeout;
            document.addEventListener('input', (e) => {
                clearTimeout(inputTimeout);
                inputTimeout = setTimeout(() => {
                    this.handleInput(e);
                }, 500);
            }, true);

            // Record navigation
            window.addEventListener('popstate', () => {
                this.recordEvent('navigation', { url: window.location.href });
            });

            // Send on page unload
            window.addEventListener('beforeunload', () => {
                this.sendEvents();
            });

            // Send periodically
            setInterval(() => {
                if (this.events.length > 100) {
                    this.sendEvents();
                }
            }, 30000); // Every 30 seconds

            if (config.debug) {
                console.log('[ErrorWatch] Session replay initialized');
            }
        },

        handleClick: function(e) {
            const target = e.target;
            this.recordEvent('click', {
                tag: target.tagName.toLowerCase(),
                id: target.id,
                class: target.className,
                text: target.innerText ? target.innerText.substring(0, 50) : null,
                x: e.clientX,
                y: e.clientY
            });
        },

        handleInput: function(e) {
            const target = e.target;
            // Don't record sensitive data
            if (target.type === 'password' || target.name?.includes('password')) {
                return;
            }
            this.recordEvent('input', {
                tag: target.tagName.toLowerCase(),
                name: target.name,
                type: target.type
            });
        },

        recordEvent: function(type, data) {
            if (this.events.length >= this.maxEvents) {
                this.events.shift();
            }

            this.events.push({
                type: type,
                timestamp: Date.now() - this.startTime,
                data: data
            });
        },

        sendEvents: function() {
            if (this.events.length === 0) return;

            const payload = {
                events: this.events,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: Date.now()
            };

            // Use sendBeacon for reliability
            if (navigator.sendBeacon) {
                navigator.sendBeacon(
                    config.endpoint + '/api/v1/replay/error',
                    JSON.stringify(payload)
                );
            } else {
                // Fallback to fetch
                fetch(config.endpoint + '/api/v1/replay/error', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + config.apiKey
                    },
                    body: JSON.stringify(payload),
                    keepalive: true
                });
            }

            if (config.debug) {
                console.log('[ErrorWatch] Sent', this.events.length, 'events');
            }

            this.events = [];
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ErrorWatchReplay.init());
    } else {
        ErrorWatchReplay.init();
    }
})();
</script>
