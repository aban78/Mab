<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Cursor IDE</title>
    <!-- Google Fonts Outfit & JetBrains Mono -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Monaco Editor Loader -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.39.0/min/vs/loader.min.js"></script>
    
    <style>
        :root {
            --bg-base: #0b0f19;
            --bg-surface: rgba(20, 26, 42, 0.65);
            --bg-surface-solid: #121824;
            --bg-active: #1d263b;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --accent-primary: #8b5cf6; /* Purple indicator */
            --accent-secondary: #ec4899; /* Pink */
            --accent-glow: rgba(139, 92, 246, 0.15);
            
            --gemini-color: #3b82f6;
            --claude-color: #f97316;
            --chatgpt-color: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.1) transparent;
        }

        /* Custom scrollbar styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-primary);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Navbar */
        .navbar {
            height: 55px;
            background: rgba(11, 15, 25, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand i {
            -webkit-text-fill-color: initial;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            padding: 8px;
            border-radius: 8px;
            font-size: 1rem;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background: var(--bg-active);
            border-color: var(--accent-primary);
            box-shadow: 0 0 10px var(--accent-glow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-primary), #7c3aed);
            border: none;
        }
        .btn-primary:hover {
            filter: brightness(1.1);
        }

        /* Main Workspace Layout */
        .workspace {
            flex: 1;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        /* Left Sidebar: File Tree */
        .file-sidebar {
            width: 250px;
            border-right: 1px solid var(--border-color);
            background: var(--bg-surface);
            backdrop-filter: blur(5px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .sidebar-icons {
            display: flex;
            gap: 8px;
            color: var(--text-secondary);
        }

        .sidebar-icons i {
            cursor: pointer;
            transition: color 0.2s;
        }

        .sidebar-icons i:hover {
            color: var(--text-primary);
        }

        .file-tree-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .file-tree {
            list-style: none;
        }

        .file-tree li {
            margin: 2px 0;
            font-size: 0.9rem;
        }

        .tree-node {
            display: flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 4px;
            cursor: pointer;
            user-select: none;
            gap: 8px;
            transition: background 0.15s;
        }

        .tree-node:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .tree-node.active {
            background: rgba(139, 92, 246, 0.15);
            color: #fff;
            border-left: 2px solid var(--accent-primary);
        }

        .tree-node i {
            width: 16px;
            text-align: center;
        }

        .tree-node i.fa-folder {
            color: #f59e0b;
        }
        .tree-node i.fa-folder-open {
            color: #f59e0b;
        }
        .tree-node i.fa-php {
            color: #8b5cf6;
        }
        .tree-node i.fa-cube { /* Dart/Flutter representation */
            color: #0284c7;
        }
        
        .tree-children {
            list-style: none;
            padding-left: 15px;
            display: none;
        }

        .tree-children.expanded {
            display: block;
        }

        /* Center Section: Editor */
        .editor-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #1e1e1e; /* Monaco default dark background matches */
            overflow: hidden;
            position: relative;
        }

        .editor-header {
            height: 40px;
            background: #181818;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 15px;
        }

        .active-tab {
            color: var(--text-primary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'JetBrains Mono', monospace;
        }

        #editor-target {
            flex: 1;
            width: 100%;
            height: 100%;
        }

        /* Right Sidebar: AI Panel */
        .ai-sidebar {
            width: 320px;
            border-left: 1px solid var(--border-color);
            background: var(--bg-surface);
            backdrop-filter: blur(5px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .ai-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ai-model-selector {
            width: 100%;
            background: var(--bg-surface-solid);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px;
            border-radius: 6px;
            outline: none;
            font-weight: 500;
        }

        .ai-model-selector option {
            background: var(--bg-base);
            color: var(--text-primary);
        }

        .chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .chat-bubble {
            padding: 12px 14px;
            border-radius: 12px;
            max-width: 90%;
            font-size: 0.9rem;
            line-height: 1.45;
            word-wrap: break-word;
        }

        .chat-bubble.user {
            background: var(--accent-primary);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }

        .chat-bubble.assistant {
            background: var(--bg-active);
            color: var(--text-primary);
            align-self: flex-start;
            border-bottom-left-radius: 2px;
            border: 1px solid var(--border-color);
        }

        .chat-bubble.system-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            align-self: center;
            border: 1px solid rgba(239, 68, 68, 0.2);
            text-align: center;
            max-width: 95%;
        }

        .chat-bubble pre {
            background: #111827;
            padding: 10px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 8px 0;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            border: 1px solid rgba(255,255,255,0.05);
            position: relative;
        }

        .code-actions {
            margin-top: 5px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .code-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .code-btn:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.15);
        }

        .chat-input-area {
            padding: 15px;
            border-top: 1px solid var(--border-color);
            background: rgba(11, 15, 25, 0.5);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .chat-input-wrapper {
            position: relative;
            display: flex;
            align-items: flex-end;
            background: var(--bg-surface-solid);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 6px 10px;
        }

        .chat-input-wrapper:focus-within {
            border-color: var(--accent-primary);
            box-shadow: 0 0 8px var(--accent-glow);
        }

        .chat-textarea {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            resize: none;
            height: 40px;
            max-height: 120px;
            font-size: 0.9rem;
            padding: 6px 0;
        }

        .chat-send-btn {
            background: var(--accent-primary);
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s, background 0.15s;
            margin-bottom: 3px;
        }

        .chat-send-btn:hover {
            background: #7c3aed;
            transform: scale(1.05);
        }

        .guide-banner {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: center;
        }

        /* Settings Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .modal {
            background: var(--bg-surface-solid);
            border: 1px solid var(--border-color);
            width: 450px;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 15px;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.open .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .close-modal:hover {
            color: var(--text-primary);
        }

        /* Modal Tabs Styling */
        .modal-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .modal-tab {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-secondary);
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            user-select: none;
        }
        
        .modal-tab.active {
            color: var(--accent-primary);
            border-bottom: 2px solid var(--accent-primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Dropzone / Upload area styling */
        .upload-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            background: var(--bg-base);
            cursor: pointer;
            transition: border-color 0.2s, background-color 0.2s;
        }
        
        .upload-dropzone:hover, .upload-dropzone.dragover {
            border-color: var(--accent-primary);
            background: rgba(139, 92, 246, 0.05);
        }
        
        .upload-dropzone i {
            font-size: 2rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: block;
        }
        
        .upload-dropzone p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .upload-progress-container {
            display: none;
            margin-top: 15px;
        }
        
        .progress-bar-wrapper {
            background: var(--border-color);
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .progress-bar-fill {
            background: var(--accent-primary);
            height: 100%;
            width: 0%;
            transition: width 0.15s;
        }
        
        .progress-text {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-input {
            background: var(--bg-base);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            outline: none;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
        }

        .form-input:focus {
            border-color: var(--accent-primary);
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: rgba(139, 92, 246, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 200;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
        }
        
        .tree-node {
            position: relative;
        }
        .tree-node .node-actions {
            display: none;
            gap: 6px;
            margin-left: auto;
            align-items: center;
        }
        .tree-node:hover .node-actions {
            display: flex;
        }
        .node-actions i {
            color: var(--text-secondary);
            font-size: 0.75rem;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.15s;
        }
        .node-actions i:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.1);
        }

        /* Collapsible Terminal Panel styles */
        .terminal-panel {
            background: #121824;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 250px;
            transition: height 0.25s ease;
            overflow: hidden;
        }
        
        .terminal-panel.collapsed {
            height: 35px;
        }
        
        .terminal-header {
            height: 35px;
            background: #0b0f19;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 15px;
            user-select: none;
        }
        
        .terminal-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .terminal-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
        }
        
        .terminal-badge.running {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .terminal-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .terminal-action-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.15s;
        }
        
        .terminal-action-btn:hover:not(:disabled) {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .terminal-action-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .terminal-btn-stop {
            color: #ef4444 !important;
        }
        
        .terminal-btn-stop:hover:not(:disabled) {
            background: rgba(239, 68, 68, 0.1) !important;
        }
        
        .terminal-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 10px 15px;
            overflow: hidden;
        }
        
        .terminal-output {
            flex: 1;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: #e5e7eb;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-all;
            margin-bottom: 8px;
        }
        
        .terminal-input-line {
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            padding-top: 6px;
        }
        
        .terminal-prompt {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: var(--accent-primary);
            font-weight: bold;
        }
        
        .terminal-cmd-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div class="brand">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <span>Internal Cursor</span>
        </div>
        
        <div class="nav-actions">
            <span id="active-workspace-display" style="font-size: 0.8rem; color: var(--text-secondary); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 6px;" title="Active Workspace">
                <i class="fa-solid fa-folder" style="color: #f59e0b;"></i> <span id="active-workspace-path-text">Default Workspace</span>
            </span>
            <button class="btn" onclick="openModal('workspace-modal')"><i class="fa-solid fa-folder-open"></i> Open Folder</button>
            <button class="btn" onclick="saveActiveFile()"><i class="fa-solid fa-floppy-disk"></i> Save <span style="font-size: 0.7rem; opacity: 0.6; margin-left: 4px;">(Ctrl+S)</span></button>
            <button class="btn" onclick="openModal('new-file-modal')"><i class="fa-solid fa-file-circle-plus"></i> New File</button>
            <button class="btn" onclick="openApkBuilderModal()" style="border-color: #10b981; color: #10b981;"><i class="fa-solid fa-cubes"></i> Build APK</button>
            <button class="btn" onclick="openModal('settings-modal')"><i class="fa-solid fa-gear"></i> Settings</button>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="workspace">
        
        <!-- Left Sidebar: File Tree -->
        <section class="file-sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">Project Files</span>
                <div class="sidebar-icons">
                    <i class="fa-solid fa-arrows-rotate" onclick="loadFileTree()" title="Refresh Explorer"></i>
                    <i class="fa-solid fa-folder-plus" onclick="openModal('new-folder-modal')" title="New Folder"></i>
                </div>
            </div>
            <div class="file-tree-container">
                <ul class="file-tree" id="file-tree-root">
                    <li style="padding: 10px; color: var(--text-secondary); text-align: center;">Loading workspace...</li>
                </ul>
            </div>
        </section>

        <!-- Center Section: Monaco Editor -->
        <section class="editor-container">
            <div class="editor-header">
                <div class="active-tab" id="active-file-tab">
                    <i class="fa-solid fa-code"></i>
                    <span id="active-file-name">No file open</span>
                </div>
                <div id="save-status" style="font-size: 0.8rem; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-cloud-arrow-up" style="color: var(--text-secondary);"></i> Ready
                </div>
            </div>
            <div id="editor-target"></div>
            
            <!-- Collapsible Terminal Panel -->
            <div class="terminal-panel collapsed" id="terminal-panel">
                <div class="terminal-header">
                    <div class="terminal-title">
                        <i class="fa-solid fa-terminal"></i>
                        <span>Terminal Console</span>
                        <span class="terminal-badge" id="terminal-status-badge">Idle</span>
                    </div>
                    <div class="terminal-actions">
                        <button class="terminal-action-btn" onclick="sendTerminalHotKey('r')" title="Hot Reload (r)" id="term-btn-reload" disabled>
                            <i class="fa-solid fa-bolt"></i> Reload
                        </button>
                        <button class="terminal-action-btn" onclick="sendTerminalHotKey('R')" title="Hot Restart (R)" id="term-btn-restart" disabled>
                            <i class="fa-solid fa-rotate-right"></i> Restart
                        </button>
                        <button class="terminal-action-btn terminal-btn-stop" onclick="stopTerminalCommand()" title="Stop Process" id="term-btn-stop" disabled>
                            <i class="fa-solid fa-circle-stop"></i> Stop
                        </button>
                        <button class="terminal-action-btn" onclick="clearTerminal()" title="Clear Terminal">
                            <i class="fa-solid fa-trash-can"></i> Clear
                        </button>
                        <button class="terminal-action-btn" onclick="toggleTerminal()" title="Toggle Terminal Size">
                            <i class="fa-solid fa-chevron-up" id="terminal-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="terminal-body" id="terminal-body">
                    <div class="terminal-output" id="terminal-output">Welcome to Internal Cursor Terminal! Type a command below and press Enter (e.g. dir, php -v, flutter devices)...</div>
                    <div class="terminal-input-line">
                        <span class="terminal-prompt">&gt;</span>
                        <input type="text" class="terminal-cmd-input" id="terminal-cmd-input" placeholder="Type command here..." onkeydown="handleTerminalInput(event)">
                    </div>
                </div>
            </div>
        </section>

        <!-- Right Sidebar: AI Assistant -->
        <section class="ai-sidebar">
            <div class="ai-header">
                <span class="sidebar-title" style="font-size: 0.8rem;">AI Coding Companion</span>
                <select id="model-select" class="ai-model-selector" onchange="onModelChange()">
                    <option value="gemini" selected>⭐ Gemini (Daily Default)</option>
                    <option value="claude">Claude (Advanced)</option>
                    <option value="chatgpt">ChatGPT (Advanced)</option>
                </select>
            </div>
            
            <div class="chat-container" id="chat-box">
                <div class="chat-bubble assistant">
                    Hi! I am your AI coding companion. Open a PHP or Dart/Flutter file, select any code segment, and ask me to help generate, refactor, or explain code.
                </div>
            </div>
            
            <div class="chat-input-area">
                <div class="chat-input-wrapper">
                    <textarea id="chat-input" class="chat-textarea" placeholder="Ask AI helper... (Shift+Enter for new line)" onkeydown="handleChatKeyDown(event)"></textarea>
                    <button class="chat-send-btn" onclick="sendChatMessage()" id="chat-send-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
                <div class="guide-banner" id="guide-banner">
                    Default model: Gemini (unlimited flat fee).
                </div>
            </div>
        </section>

    </main>

    <!-- Settings Modal -->
    <div class="modal-overlay" id="settings-modal">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title">API Keys Configuration</span>
                <i class="fa-solid fa-xmark close-modal" onclick="closeModal('settings-modal')"></i>
            </div>
            <div class="form-group">
                <label for="key-gemini">Google Gemini API Key (from AI Studio)</label>
                <input type="password" id="key-gemini" class="form-input" placeholder="AIzaSy...">
            </div>
            <div class="form-group">
                <label for="key-openai">OpenAI ChatGPT API Key</label>
                <input type="password" id="key-openai" class="form-input" placeholder="sk-proj-...">
            </div>
            <div class="form-group">
                <label for="key-claude">Anthropic Claude API Key</label>
                <input type="password" id="key-claude" class="form-input" placeholder="sk-ant-...">
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); line-height: 1.4;">
                <i class="fa-solid fa-shield-halved" style="color: var(--accent-primary);"></i> Keys are stored directly in your browser's local storage and never sent anywhere else except to the proxy API.
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button class="btn" onclick="closeModal('settings-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveSettings()">Save Configuration</button>
            </div>
        </div>
    </div>

    <!-- New File Modal -->
    <div class="modal-overlay" id="new-file-modal">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title">Create New File</span>
                <i class="fa-solid fa-xmark close-modal" onclick="closeModal('new-file-modal')"></i>
            </div>
            <div class="form-group">
                <label for="new-file-path">File Path (relative to workspace root)</label>
                <input type="text" id="new-file-path" class="form-input" placeholder="lib/main.dart or api/info.php">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button class="btn" onclick="closeModal('new-file-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="createNewFile()">Create File</button>
            </div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal-overlay" id="new-folder-modal">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title">Create New Folder</span>
                <i class="fa-solid fa-xmark close-modal" onclick="closeModal('new-folder-modal')"></i>
            </div>
            <div class="form-group">
                <label for="new-folder-path">Folder Path (relative to workspace root)</label>
                <input type="text" id="new-folder-path" class="form-input" placeholder="lib/views or api/controllers">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <button class="btn" onclick="closeModal('new-folder-modal')">Cancel</button>
                <button class="btn btn-primary" onclick="createNewFolder()">Create Folder</button>
            </div>
        </div>
    </div>

    <!-- APK Builder Modal -->
    <div class="modal-overlay" id="apk-builder-modal">
        <div class="modal" style="max-width: 650px; width: 90%;">
            <div class="modal-header">
                <span class="modal-title"><i class="fa-solid fa-cubes"></i> Generate Flutter APK</span>
                <i class="fa-solid fa-xmark close-modal" onclick="closeApkBuilderModal()"></i>
            </div>
            
            <div id="apk-build-form-section">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="apk-build-mode">Build Mode</label>
                        <select id="apk-build-mode" class="form-input" style="background: var(--bg-active); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="release" selected>Release (Optimized for store/sharing)</option>
                            <option value="debug">Debug (Fast build, hot-reloadable)</option>
                            <option value="profile">Profile (For performance testing)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="apk-target-platform">Target Architecture</label>
                        <select id="apk-target-platform" class="form-input" style="background: var(--bg-active); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="fat" selected>Fat APK (Universal, runs on all devices)</option>
                            <option value="split">Split per ABI (Smaller files for arm64/arm32/x64)</option>
                            <option value="arm64-v8a">ARM64 only (64-bit phones, standard modern)</option>
                            <option value="armeabi-v7a">ARMv7 only (Older 32-bit phones)</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 20px; margin-bottom: 15px; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" id="apk-clean" checked style="accent-color: var(--accent-primary);">
                        <span>Run <code>flutter clean</code> first (Recommended)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" id="apk-obfuscate" style="accent-color: var(--accent-primary);">
                        <span>Obfuscate Dart code (Release only)</span>
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 5px; color: var(--text-secondary);">Build Progress Log</label>
                <div id="apk-build-log" style="height: 220px; background: #070913; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; overflow-y: auto; white-space: pre-wrap; color: #10b981; line-height: 1.5; box-shadow: inset 0 2px 8px rgba(0,0,0,0.8);">Ready to generate APK. Click "Generate APK" to begin.</div>
            </div>

            <div id="apk-build-status-strip" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; padding: 8px 12px; background: var(--bg-active); border-radius: 6px; font-size: 0.85rem; border: 1px solid var(--border-color);">
                <span>Status: <strong id="apk-build-status-text" style="color: var(--text-secondary);">IDLE</strong></span>
                <span id="apk-build-timer" style="color: var(--text-secondary); font-family: 'JetBrains Mono', monospace;">00:00</span>
            </div>

            <!-- Downloadable APKs Area -->
            <div id="apk-download-area" style="display: none; margin-bottom: 15px; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 6px; padding: 12px;">
                <span style="display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: #10b981;"><i class="fa-solid fa-circle-down"></i> Generated APKs Ready for Download:</span>
                <div id="apk-download-list" style="display: flex; flex-direction: column; gap: 8px;">
                    <!-- Dynamically populated links -->
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button class="btn" id="apk-btn-close" onclick="closeApkBuilderModal()">Close</button>
                <button class="btn" id="apk-btn-stop" onclick="stopApkBuild()" style="display: none; background: #ef4444; border-color: #ef4444; color: white;"><i class="fa-solid fa-circle-stop"></i> Stop Build</button>
                <button class="btn btn-primary" id="apk-btn-start" onclick="startApkBuild()" style="background: linear-gradient(135deg, var(--accent-primary), #7c3aed); border: none;"><i class="fa-solid fa-hammer"></i> Generate APK</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast-notify">
        <i class="fa-solid fa-circle-check"></i>
        <span id="toast-message">File saved successfully!</span>
    </div>

    <!-- Open Workspace Modal -->
    <div class="modal-overlay" id="workspace-modal">
        <div class="modal" style="width: 480px;">
            <div class="modal-header">
                <span class="modal-title">Upload &amp; Open Workspace Folder</span>
                <i class="fa-solid fa-xmark close-modal" onclick="closeModal('workspace-modal')"></i>
            </div>
            
            <!-- Upload Folder (exclusive flow) -->
            <div class="upload-dropzone" onclick="triggerFolderSelect('workspace-modal-upload-input')">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Click to select a local project folder to upload</p>
                <input type="file" id="workspace-modal-upload-input" webkitdirectory multiple style="display: none;" onchange="handleFolderUpload(this, 'workspace-modal')">
            </div>
            <div class="upload-progress-container" id="workspace-modal-progress">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar-fill" id="workspace-modal-progress-bar"></div>
                </div>
                <div class="progress-text">
                    <span id="workspace-modal-progress-status">Uploading files...</span>
                    <span id="workspace-modal-progress-percent">0%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- First-Time Welcome Overlay (Workspace Required) -->
    <div class="modal-overlay" id="first-time-overlay" style="z-index: 1000; backdrop-filter: blur(15px); background: rgba(11, 15, 25, 0.95); display: none; align-items: center; justify-content: center;">
        <div class="modal" style="width: 500px; border: 1px solid rgba(139, 92, 246, 0.25); background: var(--bg-surface-solid); box-shadow: 0 0 30px rgba(139, 92, 246, 0.15);">
            <div style="text-align: center; margin-bottom: 15px;">
                <div style="font-size: 3rem; background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <h2 style="font-weight: 700; margin-top: 10px; margin-bottom: 5px; background: linear-gradient(135deg, #fff, var(--text-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Welcome to Internal Cursor</h2>
                <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; padding: 0 10px; margin: 0 0 15px 0;">
                    To start coding, please upload a project folder.
                </p>
            </div>
            
            <!-- Upload Folder (exclusive flow) -->
            <div class="upload-dropzone" onclick="triggerFolderSelect('first-time-upload-input')">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p>Click to select a local project folder to upload</p>
                <input type="file" id="first-time-upload-input" webkitdirectory multiple style="display: none;" onchange="handleFolderUpload(this, 'first-time-overlay')">
            </div>
            <div class="upload-progress-container" id="first-time-overlay-progress">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar-fill" id="first-time-overlay-progress-bar"></div>
                </div>
                <div class="progress-text">
                    <span id="first-time-overlay-progress-status">Uploading files...</span>
                    <span id="first-time-overlay-progress-percent">0%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Monaco Editor Logic -->
    <script>
        let editor = null;
        let activeFilePath = null;
        let chatHistory = [];
        let activeWorkspacePath = localStorage.getItem('active_workspace_path') || '';
        let isSwappingFile = false; // Flag to prevent auto-save on load file

        function switchModalTab(modalId, tabName) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            
            // Switch tab active state
            const tabs = modal.querySelectorAll('.modal-tab');
            tabs.forEach(tab => {
                const isMatch = (tabName === 'local' && tab.innerText.toLowerCase().includes('local')) ||
                                (tabName === 'upload' && tab.innerText.toLowerCase().includes('upload'));
                if (isMatch) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
            
            // Switch content active state
            const contents = modal.querySelectorAll('.tab-content');
            contents.forEach(content => {
                if (content.id === modalId + '-tab-' + tabName) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });
        }
        
        function triggerFolderSelect(inputId) {
            document.getElementById(inputId).click();
        }
        
        function handleFolderUpload(input, modalId) {
            const files = input.files;
            if (files.length === 0) return;
            
            const progressContainer = document.getElementById(modalId + '-progress');
            const progressBar = document.getElementById(modalId + '-progress-bar');
            const progressStatus = document.getElementById(modalId + '-progress-status');
            const progressPercent = document.getElementById(modalId + '-progress-percent');
            
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressPercent.innerText = '0%';
            progressStatus.innerText = `Packaging ${files.length} files...`;
            
            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
                formData.append('paths[]', files[i].webkitRelativePath);
            }
            
            progressStatus.innerText = 'Uploading project files...';
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload.php', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressPercent.innerText = percent + '%';
                    if (percent === 100) {
                        progressStatus.innerText = 'Writing files on server...';
                    }
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.status === 'success') {
                            progressStatus.innerText = 'Upload successful!';
                            showToast(res.message);
                            
                            localStorage.setItem('active_workspace_path', res.path);
                            activeWorkspacePath = res.path;
                            
                            closeModal(modalId);
                            const displayPath = res.path.split(/[\\/]/).pop() || res.path;
                            document.getElementById('active-workspace-path-text').innerText = displayPath;
                            document.getElementById('active-workspace-display').title = res.path;
                            
                            activeFilePath = null;
                            document.getElementById('active-file-name').innerText = "No file open";
                            editor.setValue(`<?php\n// Select a file from the explorer or create a new file to get started!\necho "Welcome to Internal Cursor!";`);
                            
                            setTimeout(() => {
                                loadFileTree();
                                progressContainer.style.display = 'none';
                                input.value = '';
                            }, 1000);
                        } else {
                            progressStatus.innerText = 'Upload failed.';
                            showToast('Upload error: ' + res.message);
                        }
                    } catch (err) {
                        progressStatus.innerText = 'Server returned invalid response.';
                        console.error(err);
                    }
                } else {
                    progressStatus.innerText = 'Server error: HTTP ' + xhr.status;
                }
            };
            
            xhr.onerror = function() {
                progressStatus.innerText = 'Network error.';
                showToast('Failed to connect to backend server');
            };
            
            xhr.send(formData);
        }

        // Check if workspace is set, otherwise force first-time overlay
        window.addEventListener('DOMContentLoaded', () => {
            const overlay = document.getElementById('first-time-overlay');
            if (!activeWorkspacePath) {
                overlay.style.display = 'flex';
                overlay.classList.add('open');
            } else {
                overlay.style.display = 'none';
                overlay.classList.remove('open');
            }
        });

        function updateSaveStatus(status) {
            const statusDiv = document.getElementById('save-status');
            if (!statusDiv) return;
            if (status === 'saving') {
                statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin" style="color: var(--accent-primary);"></i> Saving changes...`;
            } else if (status === 'saved') {
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Changes saved`;
            } else if (status === 'error') {
                statusDiv.innerHTML = `<i class="fa-solid fa-circle-xmark" style="color: #ef4444;"></i> Save error`;
            } else {
                statusDiv.innerHTML = `<i class="fa-solid fa-cloud-arrow-up" style="color: var(--text-secondary);"></i> Ready`;
            }
        }

        function autoSaveActiveFile() {
            if (!activeFilePath) return;
            const content = editor.getValue();
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'write_file',
                    path: activeFilePath,
                    content: content
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    updateSaveStatus('saved');
                    setTimeout(() => {
                        if (document.getElementById('save-status').innerText.includes('Changes saved')) {
                            updateSaveStatus('ready');
                        }
                    }, 3000);
                } else {
                    updateSaveStatus('error');
                }
            })
            .catch(err => {
                updateSaveStatus('error');
                console.error(err);
            });
        }

        // Configure Monaco Loader
        require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.39.0/min/vs' }});
        
        require(['vs/editor/editor.main'], function() {
            // Register themes or settings
            editor = monaco.editor.create(document.getElementById('editor-target'), {
                value: `<?php\n// Select a file from the explorer or create a new file to get started!\necho "Welcome to Internal Cursor!";`,
                language: 'php',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                fontFamily: "'JetBrains Mono', monospace",
                minimap: { enabled: true },
                padding: { top: 15 },
                tabSize: 4
            });

            // Keyboard Shortcut to save
            editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
                saveActiveFile();
            });

            // Setup Auto-save Content Change Event
            let autoSaveTimeout = null;
            editor.onDidChangeModelContent(() => {
                if (isSwappingFile) return;
                if (!activeFilePath) return;
                updateSaveStatus('saving');
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    autoSaveActiveFile();
                }, 1000);
            });

            // Load components
            loadSettings();
            if (activeWorkspacePath) {
                loadFileTree();
            }
        });

        // Helper to retrieve standard headers with dynamic workspace path
        function getApiHeaders(additionalHeaders = {}) {
            const headers = {
                'Content-Type': 'application/json',
                'X-Workspace-Path': activeWorkspacePath
            };
            return Object.assign(headers, additionalHeaders);
        }

        // Toggle banners depending on model selected
        function onModelChange() {
            const model = document.getElementById('model-select').value;
            const banner = document.getElementById('guide-banner');
            if (model === 'gemini') {
                banner.innerHTML = "Default model: Gemini (unlimited flat fee).";
            } else if (model === 'claude') {
                banner.innerHTML = "<span style='color: var(--claude-color)'><i class='fa-solid fa-triangle-exclamation'></i> Advanced usage: Ask Alan for permission.</span>";
            } else if (model === 'chatgpt') {
                banner.innerHTML = "<span style='color: var(--chatgpt-color)'><i class='fa-solid fa-triangle-exclamation'></i> Advanced usage: Ask Alan for permission.</span>";
            }
        }

        // Save keys
        function saveSettings() {
            localStorage.setItem('key_gemini', document.getElementById('key-gemini').value);
            localStorage.setItem('key_openai', document.getElementById('key-openai').value);
            localStorage.setItem('key_claude', document.getElementById('key-claude').value);
            closeModal('settings-modal');
            showToast('Settings saved successfully!');
        }

        function loadSettings() {
            document.getElementById('key-gemini').value = localStorage.getItem('key_gemini') || '';
            document.getElementById('key-openai').value = localStorage.getItem('key_openai') || '';
            document.getElementById('key-claude').value = localStorage.getItem('key_claude') || '';
            
            // Populate workspace inputs and display
            if (activeWorkspacePath) {
                const displayPath = activeWorkspacePath.split(/[\\/]/).pop() || activeWorkspacePath;
                document.getElementById('active-workspace-path-text').innerText = displayPath;
                document.getElementById('active-workspace-display').title = activeWorkspacePath;
            } else {
                document.getElementById('active-workspace-path-text').innerText = "Default Workspace";
                document.getElementById('active-workspace-display').title = "Default workspace root";
            }
        }

        // File Explorer Tree loading
        function loadFileTree() {
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({ action: 'list_files' })
            })
            .then(res => res.json())
            .then(data => {
                const treeRoot = document.getElementById('file-tree-root');
                treeRoot.innerHTML = '';
                if (data.status === 'success') {
                    if (data.files.length === 0) {
                        treeRoot.innerHTML = '<li style="padding: 10px; color: var(--text-secondary); text-align: center;">Workspace is empty. Create a file!</li>';
                    } else {
                        buildTreeHTML(data.files, treeRoot);
                    }
                } else {
                    showToast('Error loading files: ' + data.message);
                    treeRoot.innerHTML = `<li style="padding: 10px; color: #ef4444; text-align: center;"><i class="fa-solid fa-triangle-exclamation"></i> Load failed. Click 'Open Folder' to set a valid workspace.</li>`;
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Failed to connect to backend server');
            });
        }

        function buildTreeHTML(nodes, parentEl) {
            nodes.forEach(node => {
                const li = document.createElement('li');
                const div = document.createElement('div');
                div.className = 'tree-node';
                div.dataset.path = node.path;
                
                let iconClass = 'fa-file';
                if (node.is_dir) {
                    iconClass = 'fa-folder';
                } else {
                    // Check file ext
                    const ext = node.name.split('.').pop().toLowerCase();
                    if (ext === 'php') iconClass = 'fa-brands fa-php';
                    else if (ext === 'dart') iconClass = 'fa-solid fa-cube';
                    else if (ext === 'md') iconClass = 'fa-brands fa-markdown';
                    else if (ext === 'json') iconClass = 'fa-solid fa-brackets-curly';
                }

                if (node.is_dir) {
                    div.innerHTML = `<i class="fa-solid ${iconClass}"></i><span>${node.name}</span>
                                     <span class="node-actions">
                                         <i class="fa-solid fa-file-circle-plus" title="New File Here"></i>
                                         <i class="fa-solid fa-folder-plus" title="New Folder Here"></i>
                                     </span>`;
                } else {
                    div.innerHTML = `<i class="${iconClass}"></i><span>${node.name}</span>`;
                }
                li.appendChild(div);

                if (node.is_dir) {
                    const ul = document.createElement('ul');
                    ul.className = 'tree-children';
                    li.appendChild(ul);
                    
                    div.addEventListener('click', (e) => {
                        e.stopPropagation();
                        ul.classList.toggle('expanded');
                        const folderIcon = div.querySelector('i.fa-folder, i.fa-folder-open');
                        if (ul.classList.contains('expanded')) {
                            if (folderIcon) folderIcon.className = 'fa-solid fa-folder-open';
                            if (ul.children.length === 0 && node.children) {
                                buildTreeHTML(node.children, ul);
                            }
                        } else {
                            if (folderIcon) folderIcon.className = 'fa-solid fa-folder';
                        }
                    });

                    // Inline actions
                    const addFileBtn = div.querySelector('.fa-file-circle-plus');
                    const addFolderBtn = div.querySelector('.fa-folder-plus');

                    addFileBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        document.getElementById('new-file-path').value = node.path + '/';
                        openModal('new-file-modal');
                        setTimeout(() => document.getElementById('new-file-path').focus(), 200);
                    });

                    addFolderBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        document.getElementById('new-folder-path').value = node.path + '/';
                        openModal('new-folder-modal');
                        setTimeout(() => document.getElementById('new-folder-path').focus(), 200);
                    });
                } else {
                    div.addEventListener('click', (e) => {
                        e.stopPropagation();
                        // Active node style
                        document.querySelectorAll('.tree-node').forEach(n => n.classList.remove('active'));
                        div.classList.add('active');
                        openFile(node.path);
                    });
                }
                parentEl.appendChild(li);
            });
        }

        // Open a file
        function openFile(path) {
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({ action: 'read_file', path: path })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    activeFilePath = data.path;
                    document.getElementById('active-file-name').innerText = data.path;
                    
                    // Determine language
                    const ext = data.path.split('.').pop().toLowerCase();
                    let lang = 'plaintext';
                    if (ext === 'php') lang = 'php';
                    else if (ext === 'dart') lang = 'dart';
                    else if (ext === 'md') lang = 'markdown';
                    else if (ext === 'json') lang = 'json';
                    else if (ext === 'js') lang = 'javascript';
                    else if (ext === 'css') lang = 'css';
                    else if (ext === 'html') lang = 'html';

                    monaco.editor.setModelLanguage(editor.getModel(), lang);
                    isSwappingFile = true;
                    editor.setValue(data.content);
                    isSwappingFile = false;
                    updateSaveStatus('ready');
                    showToast('Loaded ' + data.path.split('/').pop());
                } else {
                    showToast('Failed to open file: ' + data.message);
                }
            });
        }

        // Save active file
        function saveActiveFile() {
            if (!activeFilePath) {
                showToast('No active file to save. Create/open one first.');
                return;
            }
            const content = editor.getValue();
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'write_file',
                    path: activeFilePath,
                    content: content
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast('Saved successfully');
                } else {
                    showToast('Save failed: ' + data.message);
                }
            });
        }

        // Create new file
        function createNewFile() {
            const filePath = document.getElementById('new-file-path').value.trim();
            if (!filePath) return;
            
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'write_file',
                    path: filePath,
                    content: ''
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeModal('new-file-modal');
                    document.getElementById('new-file-path').value = '';
                    showToast('File created!');
                    loadFileTree();
                    openFile(filePath);
                } else {
                    showToast('Failed to create: ' + data.message);
                }
            });
        }

        // Create new folder
        function createNewFolder() {
            const folderPath = document.getElementById('new-folder-path').value.trim();
            if (!folderPath) return;

            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'create_folder',
                    path: folderPath
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeModal('new-folder-modal');
                    document.getElementById('new-folder-path').value = '';
                    showToast('Folder created!');
                    loadFileTree();
                } else {
                    showToast('Failed to create: ' + data.message);
                }
            });
        }

        // AI Chat Functionality
        function sendChatMessage() {
            const chatInput = document.getElementById('chat-input');
            const messageText = chatInput.value.trim();
            if (!messageText) return;

            // Append user bubble
            appendChatBubble(messageText, 'user');
            chatInput.value = '';

            // Build request messages including system prompt
            let selectedModel = document.getElementById('model-select').value;
            let currentCodeSelection = editor.getSelection();
            let selectedText = editor.getModel().getValueInRange(currentCodeSelection);
            
            let systemPrompt = "You are an AI programming assistant built into an IDE called 'Internal Cursor'. "
                             + "You help developers write code, rewrite sections, fix bugs, and refactor. "
                             + "Always respond with code blocks when providing code snippets, specifying the language correctly (e.g. ```php or ```dart).\n";
            
            if (activeFilePath) {
                systemPrompt += `The developer is currently editing the file: ${activeFilePath}\n`;
            }
            if (selectedText) {
                systemPrompt += `The developer has highlighted the following code:\n\`\`\`\n${selectedText}\n\`\`\`\n`;
            }

            // Chat history formatting
            let messagesPayload = [{ role: 'system', content: systemPrompt }];
            
            // Limit history to last 10 messages for token usage efficiency
            const historySlice = chatHistory.slice(-10);
            historySlice.forEach(h => {
                messagesPayload.push({ role: h.role, content: h.content });
            });
            
            messagesPayload.push({ role: 'user', content: messageText });

            // Show temporary loading indicator bubble
            const loadingBubble = appendChatBubble('<i class="fa-solid fa-spinner fa-spin"></i> Assistant thinking...', 'assistant');

            // API keys
            const keyGemini = localStorage.getItem('key_gemini') || '';
            const keyOpenAI = localStorage.getItem('key_openai') || '';
            const keyClaude = localStorage.getItem('key_claude') || '';

            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders({
                    'X-Gemini-Key': keyGemini,
                    'X-ChatGPT-Key': keyOpenAI,
                    'X-Claude-Key': keyClaude
                }),
                body: JSON.stringify({
                    action: 'chat',
                    model: selectedModel,
                    messages: messagesPayload
                })
            })
            .then(res => res.json())
            .then(data => {
                loadingBubble.remove();
                if (data.status === 'success') {
                    // Save response to local state
                    chatHistory.push({ role: 'user', content: messageText });
                    chatHistory.push({ role: 'assistant', content: data.reply });
                    
                    const replyHTML = formatResponse(data.reply);
                    appendChatBubble(replyHTML, 'assistant', true);
                } else {
                    appendChatBubble('Error: ' + data.message, 'system-error');
                }
            })
            .catch(err => {
                loadingBubble.remove();
                appendChatBubble('Network error occurred while connecting to API.', 'system-error');
            });
        }

        // Format code blocks in chat with action buttons
        function formatResponse(text) {
            // Escape HTML tags to prevent cross-site scripting/formatting bugs, except code blocks
            const div = document.createElement('div');
            div.innerText = text;
            let escaped = div.innerHTML;

            // Find fenced code blocks
            const codeBlockRegex = /```(php|dart|javascript|css|html|json|markdown|plaintext)?\n([\s\S]*?)```/g;
            let formatted = escaped.replace(codeBlockRegex, (match, lang, code) => {
                const unescapedCode = code
                    .replace(/&amp;/g, '&')
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&quot;/g, '"')
                    .replace(/&#039;/g, "'");
                
                const codeId = 'code_' + Math.random().toString(36).substr(2, 9);
                // Store code string globally for click handler
                window[codeId] = unescapedCode;

                return `<pre><code>${code}</code><div class="code-actions">
                            <button class="code-btn" onclick="insertAtCursor(window['${codeId}'])"><i class="fa-solid fa-arrow-down-long"></i> Insert at Cursor</button>
                            <button class="code-btn" onclick="replaceActiveFile(window['${codeId}'])"><i class="fa-solid fa-file-signature"></i> Replace File</button>
                        </div></pre>`;
            });

            return formatted.replace(/\n/g, '<br>');
        }

        function insertAtCursor(text) {
            const selection = editor.getSelection();
            const range = new monaco.Range(selection.startLineNumber, selection.startColumn, selection.endLineNumber, selection.endColumn);
            const id = { major: 1, minor: 1 };
            const textOp = { identifier: id, range: range, text: text, forceMoveMarkers: true };
            editor.executeEdits("my-source", [textOp]);
            showToast('Code inserted at cursor!');
        }

        function replaceActiveFile(text) {
            if (!activeFilePath) {
                showToast('No active file open to replace.');
                return;
            }
            editor.setValue(text);
            showToast('File contents replaced with AI code!');
        }

        // DOM helper: Add bubble
        function appendChatBubble(content, role, isHTML = false) {
            const chatBox = document.getElementById('chat-box');
            const bubble = document.createElement('div');
            bubble.className = `chat-bubble ${role}`;
            if (isHTML) {
                bubble.innerHTML = content;
            } else {
                bubble.innerText = content;
            }
            chatBox.appendChild(bubble);
            chatBox.scrollTop = chatBox.scrollHeight;
            return bubble;
        }

        function handleChatKeyDown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatMessage();
            }
        }

        // Modal Helpers
        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        // Toast Helper
        function showToast(msg) {
            const toast = document.getElementById('toast-notify');
            document.getElementById('toast-message').innerText = msg;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2000);
        }

        // Terminal Logic
        let terminalPollInterval = null;
        let terminalOffset = 0;
        let isTerminalRunning = false;

        function toggleTerminal() {
            const panel = document.getElementById('terminal-panel');
            const icon = document.getElementById('terminal-toggle-icon');
            panel.classList.toggle('collapsed');
            if (panel.classList.contains('collapsed')) {
                icon.className = 'fa-solid fa-chevron-up';
            } else {
                icon.className = 'fa-solid fa-chevron-down';
                // Focus command input when expanded
                setTimeout(() => {
                    document.getElementById('terminal-cmd-input').focus();
                }, 100);
            }
        }

        function clearTerminal() {
            document.getElementById('terminal-output').innerText = '';
        }

        function handleTerminalInput(e) {
            if (e.key === 'Enter') {
                const inputEl = document.getElementById('terminal-cmd-input');
                const command = inputEl.value.trim();
                if (!command) return;

                // Clear input
                inputEl.value = '';

                // Run the command
                startTerminalCommand(command);
            }
        }

        function appendToTerminal(text) {
            const outputEl = document.getElementById('terminal-output');
            outputEl.innerText += text;
            outputEl.scrollTop = outputEl.scrollHeight;
        }

        function updateTerminalStatus(statusText, isRunning) {
            const badge = document.getElementById('terminal-status-badge');
            const btnReload = document.getElementById('term-btn-reload');
            const btnRestart = document.getElementById('term-btn-restart');
            const btnStop = document.getElementById('term-btn-stop');

            badge.innerText = statusText.toUpperCase();
            isTerminalRunning = isRunning;

            if (isRunning) {
                badge.className = 'terminal-badge running';
                btnReload.removeAttribute('disabled');
                btnRestart.removeAttribute('disabled');
                btnStop.removeAttribute('disabled');
            } else {
                badge.className = 'terminal-badge';
                btnReload.setAttribute('disabled', 'true');
                btnRestart.setAttribute('disabled', 'true');
                btnStop.setAttribute('disabled', 'true');
            }
        }

        function startTerminalCommand(command) {
            if (isTerminalRunning) {
                showToast("A command is already running!");
                return;
            }

            // Display executing command
            appendToTerminal(`\n> ${command}\n`);
            updateTerminalStatus('Starting...', true);
            terminalOffset = 0;

            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'terminal_start',
                    command: command
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Start polling
                    startTerminalPolling();
                } else {
                    appendToTerminal(`Error starting command: ${data.message}\n`);
                    updateTerminalStatus('Error', false);
                }
            })
            .catch(err => {
                appendToTerminal(`Network error starting command.\n`);
                updateTerminalStatus('Error', false);
                console.error(err);
            });
        }

        function startTerminalPolling() {
            if (terminalPollInterval) clearInterval(terminalPollInterval);
            updateTerminalStatus('Running', true);
            
            terminalPollInterval = setInterval(() => {
                fetch('api.php', {
                    method: 'POST',
                    headers: getApiHeaders(),
                    body: JSON.stringify({
                        action: 'terminal_poll',
                        offset: terminalOffset
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Append output
                        if (data.output) {
                            appendToTerminal(data.output);
                        }
                        terminalOffset = data.new_offset;

                        // Check process status
                        const procStatus = data.process_status;
                        if (procStatus && procStatus.status === 'stopped') {
                            clearInterval(terminalPollInterval);
                            terminalPollInterval = null;
                            updateTerminalStatus('Idle', false);
                        }
                    } else {
                        appendToTerminal(`\n[Polling error: ${data.message}]\n`);
                        clearInterval(terminalPollInterval);
                        terminalPollInterval = null;
                        updateTerminalStatus('Stopped', false);
                    }
                })
                .catch(err => {
                    appendToTerminal(`\n[Connection lost to server]\n`);
                    clearInterval(terminalPollInterval);
                    terminalPollInterval = null;
                    updateTerminalStatus('Disconnected', false);
                    console.error(err);
                });
            }, 200); // 200ms poll interval
        }

        function stopTerminalCommand() {
            if (!isTerminalRunning) return;
            
            updateTerminalStatus('Stopping...', true);
            
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'terminal_stop'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast('Termination request sent.');
                } else {
                    showToast('Failed to stop process: ' + data.message);
                }
            })
            .catch(err => {
                showToast('Network error while stopping process.');
                console.error(err);
            });
        }

        function sendTerminalHotKey(key) {
            if (!isTerminalRunning) return;
            
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'terminal_input',
                    text: key
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (key === 'r') {
                        showToast('Sent Hot Reload command.');
                        appendToTerminal(' [Hot Reload] ');
                    } else if (key === 'R') {
                        showToast('Sent Hot Restart command.');
                        appendToTerminal(' [Hot Restart] ');
                    } else {
                        showToast('Input sent: ' + key);
                    }
                }
            });
        }

        // APK Builder JavaScript Integration
        let apkBuildPollInterval = null;
        let apkBuildOffset = 0;
        let isApkBuilding = false;
        let apkBuildStartTime = 0;
        let apkBuildTimerInterval = null;

        function openApkBuilderModal() {
            openModal('apk-builder-modal');
            checkApkBuildStatus();
        }

        function closeApkBuilderModal() {
            if (isApkBuilding) {
                if (!confirm("A build is currently running in the background. Closing this dialog will NOT stop the build. Do you want to close this dialog?")) {
                    return;
                }
            }
            closeModal('apk-builder-modal');
        }

        function updateApkBuildTimer() {
            if (!apkBuildStartTime) return;
            const diff = Math.floor((Date.now() - apkBuildStartTime) / 1000);
            const minutes = String(Math.floor(diff / 60)).padStart(2, '0');
            const seconds = String(diff % 60).padStart(2, '0');
            document.getElementById('apk-build-timer').innerText = `${minutes}:${seconds}`;
        }

        function checkApkBuildStatus() {
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({ action: 'apk_build_status' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const statusData = data.build_status;
                    const apks = data.apks || [];
                    
                    // Display existing APKs if any
                    updateApkDownloadList(apks);

                    if (statusData && statusData.status === 'running') {
                        isApkBuilding = true;
                        apkBuildStartTime = (statusData.start_time * 1000) || Date.now();
                        apkBuildOffset = 0;
                        document.getElementById('apk-build-log').innerText = "Reconnected to active build. Fetching logs...\n";
                        
                        // Update UI state to Building
                        setApkBuildingUIState(true);
                        
                        // Start polling and timer
                        if (apkBuildPollInterval) clearInterval(apkBuildPollInterval);
                        apkBuildPollInterval = setInterval(pollApkBuildProgress, 1000);
                        
                        if (apkBuildTimerInterval) clearInterval(apkBuildTimerInterval);
                        apkBuildTimerInterval = setInterval(updateApkBuildTimer, 1000);
                    } else {
                        isApkBuilding = false;
                        setApkBuildingUIState(false);
                        document.getElementById('apk-build-status-text').innerText = "IDLE";
                        document.getElementById('apk-build-status-text').style.color = "var(--text-secondary)";
                    }
                }
            });
        }

        function setApkBuildingUIState(building) {
            const btnStart = document.getElementById('apk-btn-start');
            const btnStop = document.getElementById('apk-btn-stop');
            const formSection = document.getElementById('apk-build-form-section');
            const statusText = document.getElementById('apk-build-status-text');

            if (building) {
                btnStart.style.display = 'none';
                btnStop.style.display = 'inline-flex';
                // Disable inputs
                formSection.querySelectorAll('select, input').forEach(el => el.setAttribute('disabled', 'true'));
                statusText.innerText = "BUILDING";
                statusText.style.color = "var(--accent-primary)";
            } else {
                btnStart.style.display = 'inline-flex';
                btnStop.style.display = 'none';
                // Enable inputs
                formSection.querySelectorAll('select, input').forEach(el => el.removeAttribute('disabled'));
                if (apkBuildTimerInterval) {
                    clearInterval(apkBuildTimerInterval);
                    apkBuildTimerInterval = null;
                }
                if (apkBuildPollInterval) {
                    clearInterval(apkBuildPollInterval);
                    apkBuildPollInterval = null;
                }
            }
        }

        function startApkBuild() {
            if (isApkBuilding) return;

            const mode = document.getElementById('apk-build-mode').value;
            const target = document.getElementById('apk-target-platform').value;
            const clean = document.getElementById('apk-clean').checked;
            const obfuscate = document.getElementById('apk-obfuscate').checked;

            const logEl = document.getElementById('apk-build-log');
            logEl.innerText = "Initializing background build process...\n";
            logEl.style.color = "#8b5cf6"; // Purple color for start

            document.getElementById('apk-download-area').style.display = 'none';
            document.getElementById('apk-build-timer').innerText = "00:00";

            isApkBuilding = true;
            apkBuildStartTime = Date.now();
            apkBuildOffset = 0;
            
            setApkBuildingUIState(true);

            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'apk_build_start',
                    mode: mode,
                    target: target,
                    clean: clean,
                    obfuscate: obfuscate
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Start polling progress and timer
                    apkBuildPollInterval = setInterval(pollApkBuildProgress, 1000);
                    apkBuildTimerInterval = setInterval(updateApkBuildTimer, 1000);
                } else {
                    logEl.innerText += `\nError starting build: ${data.message}\n`;
                    logEl.style.color = "#ef4444";
                    isApkBuilding = false;
                    setApkBuildingUIState(false);
                    document.getElementById('apk-build-status-text').innerText = "ERROR";
                    document.getElementById('apk-build-status-text').style.color = "#ef4444";
                }
            })
            .catch(err => {
                logEl.innerText += `\nNetwork error starting build process.\n`;
                logEl.style.color = "#ef4444";
                isApkBuilding = false;
                setApkBuildingUIState(false);
                document.getElementById('apk-build-status-text').innerText = "ERROR";
                document.getElementById('apk-build-status-text').style.color = "#ef4444";
                console.error(err);
            });
        }

        function pollApkBuildProgress() {
            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({
                    action: 'apk_build_poll',
                    offset: apkBuildOffset
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const logEl = document.getElementById('apk-build-log');
                    
                    if (data.output) {
                        logEl.innerText += data.output;
                        logEl.scrollTop = logEl.scrollHeight;
                    }
                    apkBuildOffset = data.new_offset;

                    const buildStatus = data.build_status;
                    const statusText = document.getElementById('apk-build-status-text');

                    if (buildStatus) {
                        if (buildStatus.status === 'running') {
                            const step = buildStatus.current_step || 'running';
                            statusText.innerText = `BUILDING (${step.toUpperCase()})`;
                            logEl.style.color = "#f59e0b"; // Golden/orange when working
                        } else if (buildStatus.status === 'success') {
                            // Finished successfully
                            isApkBuilding = false;
                            setApkBuildingUIState(false);
                            statusText.innerText = "SUCCESS";
                            statusText.style.color = "#10b981";
                            logEl.style.color = "#10b981"; // Green color
                            
                            showToast("APK built successfully!");
                            
                            // Load and show download links
                            if (buildStatus.apks) {
                                updateApkDownloadList(buildStatus.apks);
                            } else {
                                // Fallback: re-check status
                                checkApkBuildStatus();
                            }
                        } else if (buildStatus.status === 'error') {
                            // Errored out
                            isApkBuilding = false;
                            setApkBuildingUIState(false);
                            statusText.innerText = "FAILED";
                            statusText.style.color = "#ef4444";
                            logEl.style.color = "#ef4444"; // Red color
                            showToast("APK build failed.");
                        } else if (buildStatus.status === 'stopped') {
                            // Cancelled
                            isApkBuilding = false;
                            setApkBuildingUIState(false);
                            statusText.innerText = "CANCELLED";
                            statusText.style.color = "var(--text-secondary)";
                            logEl.style.color = "var(--text-secondary)";
                            showToast("APK build stopped.");
                        }
                    }
                }
            })
            .catch(err => {
                console.error("Error polling APK build:", err);
            });
        }

        function stopApkBuild() {
            if (!confirm("Are you sure you want to stop the current build process?")) {
                return;
            }

            fetch('api.php', {
                method: 'POST',
                headers: getApiHeaders(),
                body: JSON.stringify({ action: 'apk_build_stop' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast("Termination request sent.");
                } else {
                    showToast("Failed to stop: " + data.message);
                }
            });
        }

        function updateApkDownloadList(apks) {
            const container = document.getElementById('apk-download-area');
            const listEl = document.getElementById('apk-download-list');
            
            listEl.innerHTML = '';
            
            if (apks && apks.length > 0) {
                container.style.display = 'block';
                apks.forEach(apk => {
                    const item = document.createElement('div');
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    item.style.justifyContent = 'space-between';
                    item.style.background = 'rgba(255,255,255,0.03)';
                    item.style.padding = '8px 12px';
                    item.style.borderRadius = '4px';
                    item.style.border = '1px solid var(--border-color)';
                    
                    item.innerHTML = `
                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: var(--text-primary);">
                            <i class="fa-solid fa-file-export" style="color: #10b981; margin-right: 6px;"></i> ${apk}
                        </span>
                        <a href="api.php?action=apk_build_download&file=${encodeURIComponent(apk)}&workspace_path=${encodeURIComponent(activeWorkspacePath)}" class="btn btn-primary" style="padding: 4px 10px; font-size: 0.8rem; background: linear-gradient(135deg, #10b981, #059669); border: none; height: auto;">
                            <i class="fa-solid fa-download"></i> Download
                        </a>
                    `;
                    listEl.appendChild(item);
                });
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>
