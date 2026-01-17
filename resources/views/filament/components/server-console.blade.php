<x-filament::widget>
    @assets
    @php
        $userFont = (string) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFont);
        $userFontSize = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFontSize);
        $userRows = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleRows);
    @endphp
    @if($userFont !== "monospace")
        <link rel="preload" href="{{ asset("storage/fonts/{$userFont}.ttf") }}" as="font" crossorigin>
        <style>
            @font-face {
                font-family: '{{ $userFont }}';
                src: url('{{ asset("storage/fonts/{$userFont}.ttf") }}');
            }
        </style>
    @endif
    @vite(['resources/js/console.js', 'resources/css/console.css'])
    @endassets

    <div style="position: relative;">
        <div id="terminal" wire:ignore></div>
        <button
            id="open-console-window"
            type="button"
            class="absolute top-2 right-2 p-2 rounded-md bg-gray-800/80 hover:bg-gray-700/90 transition-colors z-10"
            title="Open console in new window"
        >
            <x-filament::icon
                icon="tabler-external-link"
                class="h-5 w-5 text-gray-300"
            />
        </button>
    </div>

    @if ($this->authorizeSendCommand())
        <div class="flex items-center w-full border-top overflow-hidden dark:bg-gray-900"
             style="border-bottom-right-radius: 10px; border-bottom-left-radius: 10px;">
            <x-filament::icon
                icon="tabler-chevrons-right"
            />
            <input
                id="send-command"
                class="w-full focus:outline-none focus:ring-0 border-none dark:bg-gray-900 p-1"
                type="text"
                :readonly="{{ $this->canSendCommand() ? 'false' : 'true' }}"
                title="{{ $this->canSendCommand() ? '' : trans('server/console.command_blocked_title') }}"
                placeholder="{{ $this->canSendCommand() ? trans('server/console.command') : trans('server/console.command_blocked') }}"
                wire:model="input"
                wire:keydown.enter="enter"
                wire:keydown.up.prevent="up"
                wire:keydown.down="down"
            >
        </div>
    @endif

    @script
    <script>
        let theme = {
            background: 'rgba(19,26,32,0.7)',
            cursor: 'transparent',
            black: '#000000',
            red: '#E54B4B',
            green: '#9ECE58',
            yellow: '#FAED70',
            blue: '#396FE2',
            magenta: '#BB80B3',
            cyan: '#2DDAFD',
            white: '#d0d0d0',
            brightBlack: 'rgba(255, 255, 255, 0.2)',
            brightRed: '#FF5370',
            brightGreen: '#C3E88D',
            brightYellow: '#FFCB6B',
            brightBlue: '#82AAFF',
            brightMagenta: '#C792EA',
            brightCyan: '#89DDFF',
            brightWhite: '#ffffff',
            selection: '#FAF089'
        };

        let options = {
            fontSize: {{ $userFontSize }},
            fontFamily: '{{ $userFont }}, monospace',
            lineHeight: 1.2,
            disableStdin: true,
            cursorStyle: 'underline',
            cursorInactiveStyle: 'underline',
            allowTransparency: true,
            rows: {{ $userRows }},
            theme: theme
        };

        const { Terminal, FitAddon, WebLinksAddon, SearchAddon, SearchBarAddon, WebglAddon } = window.Xterm;

        const terminal = new Terminal(options);
        const fitAddon = new FitAddon();
        const webLinksAddon = new WebLinksAddon();
        const searchAddon = new SearchAddon();
        const searchAddonBar = new SearchBarAddon({ searchAddon });
        const webglAddon = new WebglAddon();
        terminal.loadAddon(fitAddon);
        terminal.loadAddon(webLinksAddon);
        terminal.loadAddon(searchAddon);
        terminal.loadAddon(searchAddonBar);
        terminal.loadAddon(webglAddon);

        terminal.open(document.getElementById('terminal'));

        fitAddon.fit(); // Fixes SPA issues.

        window.addEventListener('load', () => {
            fitAddon.fit();
        });

        window.addEventListener('resize', () => {
            fitAddon.fit();
        });

        terminal.attachCustomKeyEventHandler((event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
                navigator.clipboard.writeText(terminal.getSelection());
                return false;
            } else if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                searchAddonBar.show();
                return false;
            } else if (event.key === 'Escape') {
                searchAddonBar.hidden();
            }
            return true;
        });

        const TERMINAL_PRELUDE = '\u001b[1m\u001b[33mpelican@' + '{{ \Filament\Facades\Filament::getTenant()->name }}' + ' ~ \u001b[0m';

        const handleConsoleOutput = (line, prelude = false) =>
            terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handleTransferStatus = (status) =>
            status === 'failure' && terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');

        const handleDaemonErrorOutput = (line) =>
            terminal.writeln(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handlePowerChangeEvent = (state) =>
            terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

        const socket = new WebSocket("{{ $this->getSocket() }}");

        socket.onerror = (event) => {
            $wire.dispatchSelf('websocket-error');
        };

        socket.onmessage = function(websocketMessageEvent) {
            let { event, args } = JSON.parse(websocketMessageEvent.data);

            switch (event) {
                case 'console output':
                case 'install output':
                    handleConsoleOutput(args[0]);
                    break;
                case 'feature match':
                    Livewire.dispatch('mount-feature', { data: args[0] });
                    break;
                case 'status':
                    handlePowerChangeEvent(args[0]);

                    $wire.dispatch('console-status', { state: args[0] });
                    break;
                case 'transfer status':
                    handleTransferStatus(args[0]);
                    break;
                case 'daemon error':
                    handleDaemonErrorOutput(args[0]);
                    break;
                case 'stats':
                    $wire.dispatchSelf('store-stats', { data: args[0] });
                    break;
                case 'auth success':
                    socket.send(JSON.stringify({
                        'event': 'send logs',
                        'args': [null]
                    }));
                    break;
                case 'token expiring':
                case 'token expired':
                    $wire.dispatchSelf('token-request');
                    break;
            }
        };

        socket.onopen = (event) => {
            $wire.dispatchSelf('token-request');
        };

        Livewire.on('setServerState', ({ state, uuid }) => {
            const serverUuid = "{{ $this->server->uuid }}";
            if (uuid !== serverUuid) {
                return;
            }

            socket.send(JSON.stringify({
                'event': 'set state',
                'args': [state]
            }));
        });

        $wire.on('sendAuthRequest', ({ token }) => {
            socket.send(JSON.stringify({
                'event': 'auth',
                'args': [token]
            }));
        });

        $wire.on('sendServerCommand', ({ command }) => {
            socket.send(JSON.stringify({
                'event': 'send command',
                'args': [command]
            }));
        });

        // Store message handlers for external windows
        window.consoleMessageHandlers = window.consoleMessageHandlers || [];
        
        // Open console in new window functionality
        document.getElementById('open-console-window').addEventListener('click', function() {
            const newWindow = window.open('', 'Console_{{ $this->server->name }}', 'width=900,height=600,location=yes,menubar=no,toolbar=no,status=no,resizable=yes');
            
            if (newWindow) {
                // Create the HTML structure for the new window
                newWindow.document.write(`<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Console - {{ addslashes($this->server->name) }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/css/xterm.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #131a20;
            font-family: monospace;
            overflow: hidden;
        }
        #url-bar {
            background: #1f2937;
            color: #d1d5db;
            padding: 8px 12px;
            border-bottom: 1px solid #374151;
            font-size: 12px;
        }
        #url-bar input {
            width: 100%;
            background: #374151;
            border: 1px solid #4b5563;
            border-radius: 4px;
            padding: 4px 8px;
            color: #d1d5db;
            font-size: 12px;
            font-family: monospace;
        }
        #terminal-container {
            height: calc(100vh - 40px);
            background: rgba(19,26,32,0.7);
        }
        .xterm {
            padding: 10px;
            height: 100%;
        }
        .xterm-viewport {
            overflow-y: auto !important;
        }
        ::-webkit-scrollbar {
            background: none;
            width: 14px;
            height: 14px;
        }
        ::-webkit-scrollbar-thumb {
            border: solid 0 rgb(0 0 0 / 0%);
            border-right-width: 4px;
            border-left-width: 4px;
            -webkit-border-radius: 9px 4px;
            -webkit-box-shadow: inset 0 0 0 1px hsl(211, 10%, 53%), inset 0 0 0 4px hsl(209deg 18% 30%);
        }
        ::-webkit-scrollbar-track-piece {
            margin: 4px 0;
        }
        ::-webkit-scrollbar-corner {
            background: transparent;
        }
    </style>
</head>
<body>
    <div id="url-bar">
        <input type="text" readonly value="${window.location.href}" onclick="this.select()">
    </div>
    <div id="terminal-container"></div>
    <script src="https://cdn.jsdelivr.net/npm/@xterm/xterm@5.5.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10.0/lib/addon-fit.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-web-links@0.11.0/lib/addon-web-links.js"></script>
    <script>
        const theme = ${JSON.stringify(theme)};
        const termOptions = {
            fontSize: ${$userFontSize},
            fontFamily: '{{ $userFont }}, monospace',
            lineHeight: 1.2,
            disableStdin: true,
            cursorStyle: 'underline',
            cursorInactiveStyle: 'underline',
            allowTransparency: true,
            theme: theme
        };
        
        const term = new Terminal.Terminal(termOptions);
        const fitAddon = new FitAddon.FitAddon();
        const webLinksAddon = new WebLinksAddon.WebLinksAddon();
        
        term.loadAddon(fitAddon);
        term.loadAddon(webLinksAddon);
        term.open(document.getElementById('terminal-container'));
        fitAddon.fit();
        
        window.addEventListener('resize', () => {
            fitAddon.fit();
        });
        
        term.attachCustomKeyEventHandler((event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
                navigator.clipboard.writeText(term.getSelection());
                return false;
            }
            return true;
        });
        
        // This will receive messages from the parent window
        window.writeToTerminal = function(data) {
            term.writeln(data);
        };
        
        // Request buffer content from parent
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ type: 'request-buffer' }, '*');
        }
    </script>
</body>
</html>`);
                
                newWindow.document.close();
                
                // Send current terminal buffer to new window
                const buffer = terminal.buffer.active;
                for (let i = 0; i < buffer.length; i++) {
                    const line = buffer.getLine(i);
                    if (line) {
                        newWindow.writeToTerminal(line.translateToString(true));
                    }
                }
                
                // Create a handler for this window
                const handler = function(websocketMessageEvent) {
                    if (newWindow.closed) {
                        return;
                    }
                    
                    try {
                        let { event, args } = JSON.parse(websocketMessageEvent.data);
                        
                        switch (event) {
                            case 'console output':
                            case 'install output':
                                newWindow.writeToTerminal(args[0].replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');
                                break;
                            case 'status':
                                newWindow.writeToTerminal(TERMINAL_PRELUDE + 'Server marked as ' + args[0] + '...\u001b[0m');
                                break;
                            case 'transfer status':
                                if (args[0] === 'failure') {
                                    newWindow.writeToTerminal(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');
                                }
                                break;
                            case 'daemon error':
                                newWindow.writeToTerminal(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + args[0].replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');
                                break;
                        }
                    } catch (e) {
                        // Window might be closing
                    }
                };
                
                // Add this handler to our list
                window.consoleMessageHandlers.push({ window: newWindow, handler: handler });
                
                // Override socket.onmessage to call all handlers
                const originalOnMessage = socket.onmessage;
                socket.onmessage = function(websocketMessageEvent) {
                    // Call original handler
                    originalOnMessage.call(socket, websocketMessageEvent);
                    
                    // Call all external window handlers
                    window.consoleMessageHandlers = window.consoleMessageHandlers.filter(item => {
                        if (item.window.closed) {
                            return false;
                        }
                        try {
                            item.handler(websocketMessageEvent);
                            return true;
                        } catch (e) {
                            return false;
                        }
                    });
                };
                
                // Clean up when main window closes
                window.addEventListener('beforeunload', () => {
                    if (!newWindow.closed) {
                        newWindow.close();
                    }
                });
            }
        });
    </script>
    @endscript
</x-filament::widget>
