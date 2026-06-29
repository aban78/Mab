import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  runApp(const InternalCursorApp());
}

class InternalCursorApp extends StatelessWidget {
  const InternalCursorApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Internal Cursor',
      debugShowCheckedModeBanner: false,
      theme: ThemeData.dark().copyWith(
        scaffoldBackgroundColor: const Color(0xFF0B0F19),
        colorScheme: const ColorScheme.dark(
          primary: Color(0xFF8B5CF6),
          secondary: Color(0xFFEC4899),
          surface: Color(0xFF121824),
          background: Color(0xFF0B0F19),
          error: Colors.redAccent,
        ),
        cardTheme: const CardThemeData(
          color: Color(0xFF141A2A),
          elevation: 0,
        ),
      ),
      home: const MainScreen(),
    );
  }
}

// Custom Syntax Highlighting controller for PHP & Dart
class SyntaxHighlighterController extends TextEditingController {
  SyntaxHighlighterController({super.text});

  @override
  TextSpan buildTextSpan({required BuildContext context, TextStyle? style, required bool withComposing}) {
    final textVal = text;
    final List<TextSpan> children = [];
    
    // Simple token matching
    final keywordRegEx = RegExp(r'\b(class|function|var|void|import|return|if|else|for|while|foreach|public|protected|private|static|new|extends|implements|as|dynamic|final|const|late|interface|namespace|use|require|require_once|include|include_once|print|echo)\b');
    final stringRegEx = RegExp(r'"[^"]*"|' + r"'" + r"[^']*'" + r'|`[^`]*`');
    final commentRegEx = RegExp(r'//.*|/\*[\s\S]*?\*/|#.*');
    final numberRegEx = RegExp(r'\b\d+\b');

    final combinedRegEx = RegExp(
      '(${commentRegEx.pattern})|(${stringRegEx.pattern})|(${keywordRegEx.pattern})|(${numberRegEx.pattern})',
      multiLine: true,
    );

    int lastMatchEnd = 0;
    combinedRegEx.allMatches(textVal).forEach((match) {
      if (match.start > lastMatchEnd) {
        children.add(TextSpan(text: textVal.substring(lastMatchEnd, match.start), style: style));
      }

      if (match.group(1) != null) {
        // Comment (Green)
        children.add(TextSpan(text: match.group(0), style: const TextStyle(color: Color(0xFF10B981), fontStyle: FontStyle.italic)));
      } else if (match.group(2) != null) {
        // String (Amber)
        children.add(TextSpan(text: match.group(0), style: const TextStyle(color: Color(0xFFF59E0B))));
      } else if (match.group(3) != null) {
        // Keyword (Purple)
        children.add(TextSpan(text: match.group(0), style: const TextStyle(color: Color(0xFFA78BFA), fontWeight: FontWeight.bold)));
      } else if (match.group(4) != null) {
        // Number (Cyan)
        children.add(TextSpan(text: match.group(0), style: const TextStyle(color: Color(0xFF22D3EE))));
      }

      lastMatchEnd = match.end;
    });

    if (lastMatchEnd < textVal.length) {
      children.add(TextSpan(text: textVal.substring(lastMatchEnd), style: style));
    }

    return TextSpan(children: children, style: style);
  }
}

class MainScreen extends StatefulWidget {
  const MainScreen({super.key});

  @override
  State<MainScreen> createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  // App configurations
  String backendUrl = 'http://localhost:8000';
  String geminiKey = '';
  String openaiKey = '';
  String claudeKey = '';
  String activeWorkspacePath = '';

  // App state
  List<dynamic> fileTree = [];
  List<dynamic> serverWorkspaces = [];
  String? activeFilePath;
  final SyntaxHighlighterController codeController = SyntaxHighlighterController();
  final TextEditingController chatInputController = TextEditingController();
  final ScrollController chatScrollController = ScrollController();
  
  List<Map<String, String>> chatHistory = [];
  String selectedModel = 'gemini';
  bool isLoading = false;
  bool isSaving = false;
  String editorSaveStatus = 'ready'; // 'ready', 'saving', 'saved', 'error'
  Timer? autoSaveTimer;
  
  // Mobile navigation index
  int mobileIndex = 0;

  @override
  void initState() {
    super.initState();
    _loadSettings().then((_) {
      if (activeWorkspacePath.isEmpty) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _showFirstTimeWorkspaceDialog();
        });
      } else {
        _fetchFileTree();
      }
    });
  }

  Future<void> _loadSettings() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() {
      backendUrl = prefs.getString('backendUrl') ?? 'http://localhost:8000';
      geminiKey = prefs.getString('geminiKey') ?? '';
      openaiKey = prefs.getString('openaiKey') ?? '';
      claudeKey = prefs.getString('claudeKey') ?? '';
      activeWorkspacePath = prefs.getString('activeWorkspacePath') ?? '';
    });
    await _fetchServerWorkspaces();
  }

  Future<void> _fetchServerWorkspaces() async {
    if (backendUrl.isEmpty) return;
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'action': 'list_workspaces'}),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          setState(() {
            serverWorkspaces = data['workspaces'] ?? [];
          });
        }
      }
    } catch (e) {
      // Fail silently
    }
  }

  Future<void> _saveSettings() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('backendUrl', backendUrl);
    await prefs.setString('geminiKey', geminiKey);
    await prefs.setString('openaiKey', openaiKey);
    await prefs.setString('claudeKey', claudeKey);
    await prefs.setString('activeWorkspacePath', activeWorkspacePath);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Settings saved successfully')),
    );
    _fetchFileTree();
  }

  Future<void> _fetchFileTree() async {
    if (activeWorkspacePath.isEmpty) return;
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
        },
        body: jsonEncode({'action': 'list_files'}),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          setState(() {
            fileTree = data['files'];
          });
        }
      }
    } catch (e) {
      // Fail silently
    }
  }

  Future<void> _openFile(String path) async {
    setState(() {
      isLoading = true;
    });
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
        },
        body: jsonEncode({'action': 'read_file', 'path': path}),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          setState(() {
            activeFilePath = data['path'];
            codeController.text = data['content'];
            editorSaveStatus = 'ready';
            // If on mobile, switch to Editor tab (index 1)
            mobileIndex = 1;
          });
        } else {
          _showError(data['message']);
        }
      }
    } catch (e) {
      _showError('Failed to load file from backend server');
    } finally {
      setState(() {
        isLoading = false;
      });
    }
  }

  Future<void> _saveFile() async {
    if (activeFilePath == null) return;
    setState(() {
      isSaving = true;
    });
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
        },
        body: jsonEncode({
          'action': 'write_file',
          'path': activeFilePath,
          'content': codeController.text,
        }),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          setState(() {
            editorSaveStatus = 'saved';
          });
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('File saved successfully')),
          );
        } else {
          _showError(data['message']);
        }
      }
    } catch (e) {
      _showError('Failed to save file');
    } finally {
      setState(() {
        isSaving = false;
      });
    }
  }

  Future<void> _createNewFile(String path) async {
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
        },
        body: jsonEncode({
          'action': 'write_file',
          'path': path,
          'content': '',
        }),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          _fetchFileTree();
          _openFile(path);
        } else {
          _showError(data['message']);
        }
      }
    } catch (e) {
      _showError('Failed to create file');
    }
  }

  Future<void> _createNewFolder(String path) async {
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
        },
        body: jsonEncode({
          'action': 'create_folder',
          'path': path,
        }),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          _fetchFileTree();
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Folder "$path" created successfully')),
          );
        } else {
          _showError(data['message']);
        }
      }
    } catch (e) {
      _showError('Failed to create folder');
    }
  }

  void _triggerAutoSave() {
    if (activeFilePath == null) return;
    setState(() {
      editorSaveStatus = 'saving';
    });
    if (autoSaveTimer?.isActive ?? false) autoSaveTimer!.cancel();
    autoSaveTimer = Timer(const Duration(seconds: 1), () {
      _autoSaveFile();
    });
  }

  Future<void> _autoSaveFile() async {
    if (activeFilePath == null) return;
    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
        },
        body: jsonEncode({
          'action': 'write_file',
          'path': activeFilePath,
          'content': codeController.text,
        }),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          setState(() {
            editorSaveStatus = 'saved';
          });
          Timer(const Duration(seconds: 3), () {
            if (editorSaveStatus == 'saved') {
              setState(() {
                editorSaveStatus = 'ready';
              });
            }
          });
        } else {
          setState(() {
            editorSaveStatus = 'error';
          });
        }
      } else {
        setState(() {
          editorSaveStatus = 'error';
        });
      }
    } catch (e) {
      setState(() {
        editorSaveStatus = 'error';
      });
    }
  }

  void _showFirstTimeWorkspaceDialog() {
    String? selectedPath;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return WillPopScope(
              onWillPop: () async => false,
              child: AlertDialog(
                title: Row(
                  children: const [
                    Icon(Icons.auto_awesome, color: Color(0xFF8B5CF6)),
                    SizedBox(width: 8),
                    Text('Welcome to Internal Cursor'),
                  ],
                ),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Please select a workspace project already uploaded to the server to get started.',
                      style: TextStyle(fontSize: 13, color: Colors.grey),
                    ),
                    const SizedBox(height: 16),
                    serverWorkspaces.isEmpty
                        ? const Text(
                            'No uploaded workspaces found on server.\n\nPlease upload a project using the Web IDE first, or check your Backend URL in Settings.',
                            style: TextStyle(color: Colors.redAccent, fontSize: 12),
                          )
                        : Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                            decoration: BoxDecoration(
                              border: Border.all(color: Colors.white24),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: DropdownButtonHideUnderline(
                              child: DropdownButton<String>(
                                value: selectedPath,
                                hint: const Text('Select a workspace...'),
                                isExpanded: true,
                                items: serverWorkspaces.map<DropdownMenuItem<String>>((workspace) {
                                  return DropdownMenuItem<String>(
                                    value: workspace['path'] as String,
                                    child: Text(workspace['name'] as String),
                                  );
                                }).toList(),
                                onChanged: (val) {
                                  setDialogState(() {
                                    selectedPath = val;
                                  });
                                },
                              ),
                            ),
                          ),
                  ],
                ),
                actions: [
                  TextButton(
                    onPressed: () {
                      Navigator.pop(ctx);
                      setState(() {
                        mobileIndex = 3; // Settings tab
                      });
                    },
                    child: const Text('Configure Settings'),
                  ),
                  if (serverWorkspaces.isNotEmpty)
                    ElevatedButton.icon(
                      onPressed: selectedPath == null
                          ? null
                          : () async {
                              final path = selectedPath!;
                              Navigator.pop(ctx);
                              final prefs = await SharedPreferences.getInstance();
                              await prefs.setString('activeWorkspacePath', path);
                              
                              setState(() {
                                activeWorkspacePath = path;
                              });
                              
                              _fetchFileTree();
                            },
                      icon: const Icon(Icons.folder_open),
                      label: const Text('Open Project'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF8B5CF6),
                        foregroundColor: Colors.white,
                      ),
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  void _showCreateFolderDialog({String? parentFolder}) {
    final textController = TextEditingController(text: parentFolder != null ? '$parentFolder/' : '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('New Folder'),
        content: TextField(
          controller: textController,
          decoration: const InputDecoration(hintText: 'lib/views or api/controllers'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              _createNewFolder(textController.text.trim());
            },
            child: const Text('Create'),
          ),
        ],
      ),
    );
  }

  void _showCreateFileDialog({String? parentFolder}) {
    final textController = TextEditingController(text: parentFolder != null ? '$parentFolder/' : '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('New File'),
        content: TextField(
          controller: textController,
          decoration: const InputDecoration(hintText: 'lib/widgets/my_widget.dart'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () {
              Navigator.pop(ctx);
              _createNewFile(textController.text.trim());
            },
            child: const Text('Create'),
          ),
        ],
      ),
    );
  }

  Future<void> _sendChatMessage() async {
    final query = chatInputController.text.trim();
    if (query.isEmpty) return;

    setState(() {
      chatHistory.add({'role': 'user', 'content': query});
      chatInputController.clear();
      isLoading = true;
    });

    _scrollChatToEnd();

    String systemPrompt = "You are an AI programming assistant built into 'Internal Cursor' editor. "
        "Format your code responses nicely using markdown. Avoid long text explanations; output clean code.\n";
    if (activeFilePath != null) {
      systemPrompt += "The user is currently editing file: $activeFilePath\n";
    }

    final List<Map<String, String>> messagesPayload = [
      {'role': 'system', 'content': systemPrompt}
    ];
    for (var chat in chatHistory.reversed.take(6).toList().reversed) {
      messagesPayload.add({'role': chat['role']!, 'content': chat['content']!});
    }

    try {
      final response = await http.post(
        Uri.parse('$backendUrl/api.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Workspace-Path': activeWorkspacePath,
          'X-Gemini-Key': geminiKey,
          'X-ChatGPT-Key': openaiKey,
          'X-Claude-Key': claudeKey,
        },
        body: jsonEncode({
          'action': 'chat',
          'model': selectedModel,
          'messages': messagesPayload,
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['status'] == 'success') {
          setState(() {
            chatHistory.add({'role': 'assistant', 'content': data['reply']});
          });
        } else {
          setState(() {
            chatHistory.add({'role': 'system-error', 'content': data['message']});
          });
        }
      } else {
        setState(() {
          chatHistory.add({'role': 'system-error', 'content': 'Server error: ${response.statusCode}'});
        });
      }
    } catch (e) {
      setState(() {
        chatHistory.add({'role': 'system-error', 'content': 'Failed to connect to backend server API.'});
      });
    } finally {
      setState(() {
        isLoading = false;
      });
      _scrollChatToEnd();
    }
  }

  void _scrollChatToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (chatScrollController.hasClients) {
        chatScrollController.animateTo(
          chatScrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _showError(String message) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Error'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK'),
          )
        ],
      ),
    );
  }

  Widget _buildSaveStatusIndicator() {
    switch (editorSaveStatus) {
      case 'saving':
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: const [
            SizedBox(
              width: 12,
              height: 12,
              child: CircularProgressIndicator(strokeWidth: 1.5, color: Color(0xFF8B5CF6)),
            ),
            SizedBox(width: 6),
            Text('Saving...', style: TextStyle(fontSize: 11, color: Colors.grey)),
          ],
        );
      case 'saved':
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: const [
            Icon(Icons.check_circle_outline, color: Colors.green, size: 14),
            SizedBox(width: 4),
            Text('Saved', style: TextStyle(fontSize: 11, color: Colors.green)),
          ],
        );
      case 'error':
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: const [
            Icon(Icons.error_outline, color: Colors.red, size: 14),
            SizedBox(width: 4),
            Text('Save Error', style: TextStyle(fontSize: 11, color: Colors.red)),
          ],
        );
      default:
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: const [
            Icon(Icons.cloud_done_outlined, color: Colors.grey, size: 14),
            SizedBox(width: 4),
            Text('Synced', style: TextStyle(fontSize: 11, color: Colors.grey)),
          ],
        );
    }
  }

  // Helper widget to build the recursive file explorer nodes
  Widget _buildFileNode(dynamic node, {double depth = 0}) {
    final isDir = node['is_dir'] == true;
    final name = node['name'] as String;
    final path = node['path'] as String;

    if (isDir) {
      return ExpansionTile(
        tilePadding: EdgeInsets.only(left: depth * 16.0, right: 8),
        leading: const Icon(Icons.folder, color: Colors.amber, size: 20),
        title: Text(name, style: const TextStyle(fontSize: 14)),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(
              icon: const Icon(Icons.note_add_outlined, size: 16, color: Colors.grey),
              onPressed: () => _showCreateFileDialog(parentFolder: path),
              tooltip: 'New File Here',
            ),
            IconButton(
              icon: const Icon(Icons.create_new_folder_outlined, size: 16, color: Colors.grey),
              onPressed: () => _showCreateFolderDialog(parentFolder: path),
              tooltip: 'New Folder Here',
            ),
          ],
        ),
        children: (node['children'] as List<dynamic>?)
                ?.map((child) => _buildFileNode(child, depth: depth + 1))
                .toList() ??
            [],
      );
    } else {
      final isPHP = name.endsWith('.php');
      final isDart = name.endsWith('.dart');
      
      return ListTile(
        contentPadding: EdgeInsets.only(left: (depth * 16.0) + 16.0, right: 8),
        dense: true,
        leading: Icon(
          isPHP ? Icons.code : (isDart ? Icons.category : Icons.description),
          color: isPHP ? Colors.purpleAccent : (isDart ? Colors.blue : Colors.grey),
          size: 16,
        ),
        title: Text(name, style: const TextStyle(fontSize: 13)),
        selected: activeFilePath == path,
        selectedTileColor: const Color(0xFF1D263B),
        onTap: () => _openFile(path),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    // Determine screen size for responsiveness
    final screenWidth = MediaQuery.of(context).size.width;
    final isDesktop = screenWidth > 800;

    // Component widgets
    final explorerWidget = Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12.0),
          child: Row(
            mainAxisAlignment: MainbarAxisAlignment.spaceBetween,
            children: [
              const Text('PROJECT FILES', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey)),
              Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.refresh, size: 18),
                    onPressed: _fetchFileTree,
                    tooltip: 'Refresh Workspace',
                  ),
                  IconButton(
                    icon: const Icon(Icons.note_add_outlined, size: 18),
                    onPressed: () => _showCreateFileDialog(),
                    tooltip: 'New File',
                  ),
                  IconButton(
                    icon: const Icon(Icons.create_new_folder_outlined, size: 18),
                    onPressed: () => _showCreateFolderDialog(),
                    tooltip: 'New Folder',
                  ),
                ],
              )
            ],
          ),
        ),
        Expanded(
          child: fileTree.isEmpty
              ? const Center(child: Text('Workspace empty', style: TextStyle(color: Colors.grey)))
              : ListView(
                  children: fileTree.map((node) => _buildFileNode(node)).toList(),
                ),
        ),
      ],
    );

    final editorWidget = Column(
      children: [
        Container(
          color: const Color(0xFF121824),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            children: [
              const Icon(Icons.edit_note, size: 20, color: Color(0xFF8B5CF6)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  activeFilePath ?? 'No file open',
                  style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (activeFilePath != null) ...[
                _buildSaveStatusIndicator(),
                const SizedBox(width: 12),
                isSaving
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : ElevatedButton.icon(
                        onPressed: _saveFile,
                        icon: const Icon(Icons.save, size: 14),
                        label: const Text('Save', style: TextStyle(fontSize: 12)),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        ),
                      ),
              ],
            ],
          ),
        ),
        Expanded(
          child: Container(
            color: const Color(0xFF1E1E1E),
            padding: const EdgeInsets.all(12),
            child: activeFilePath == null
                ? const Center(child: Text('Open a file to begin coding', style: TextStyle(color: Colors.grey)))
                : TextField(
                    controller: codeController,
                    maxLines: null,
                    keyboardType: TextInputType.multiline,
                    style: const TextStyle(fontFamily: 'monospace', fontSize: 13, height: 1.4),
                    onChanged: (text) {
                      _triggerAutoSave();
                    },
                    decoration: const InputDecoration(
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
          ),
        ),
      ],
    );

    final chatWidget = Column(
      children: [
        Container(
          color: const Color(0xFF121824),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: [
              const Text('MODEL: ', style: TextStyle(fontSize: 11, color: Colors.grey)),
              Expanded(
                child: DropdownButton<String>(
                  value: selectedModel,
                  isExpanded: true,
                  underline: const SizedBox(),
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.white),
                  items: const [
                    DropdownMenuItem(value: 'gemini', child: Text('⭐ Gemini (Routine)')),
                    DropdownMenuItem(value: 'claude', child: Text('Claude (Advanced)')),
                    DropdownMenuItem(value: 'chatgpt', child: Text('ChatGPT (Advanced)')),
                  ],
                  onChanged: (val) {
                    if (val != null) {
                      setState(() {
                        selectedModel = val;
                      });
                      if (val == 'claude' || val == 'chatgpt') {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Claude & ChatGPT are billed per-token. Get permission from Alan.')),
                        );
                      }
                    }
                  },
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView.builder(
            controller: chatScrollController,
            padding: const EdgeInsets.all(12),
            itemCount: chatHistory.length,
            itemBuilder: (ctx, index) {
              final chat = chatHistory[index];
              final isUser = chat['role'] == 'user';
              final isError = chat['role'] == 'system-error';
              
              return Align(
                alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
                child: Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: isUser
                        ? const Color(0xFF8B5CF6)
                        : (isError ? Colors.red.withOpacity(0.15) : const Color(0xFF141A2A)),
                    borderRadius: BorderRadius.circular(8).copyWith(
                      bottomRight: isUser ? const Radius.circular(0) : null,
                      bottomLeft: !isUser ? const Radius.circular(0) : null,
                    ),
                    border: !isUser && !isError ? Border.all(color: Colors.white.withOpacity(0.05)) : null,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        chat['content']!,
                        style: const TextStyle(fontSize: 13, height: 1.3),
                      ),
                      if (!isUser && !isError && chat['content']!.contains('```'))
                        Padding(
                          padding: const EdgeInsets.only(top: 6.0),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.end,
                            children: [
                              TextButton.icon(
                                onPressed: () {
                                  // Simple code block extraction
                                  final text = chat['content']!;
                                  final start = text.indexOf('```');
                                  if (start != -1) {
                                    final end = text.indexOf('```', start + 3);
                                    if (end != -1) {
                                      // Get lines and remove first line (specifying language)
                                      final block = text.substring(start + 3, end).trim();
                                      final lines = block.split('\n');
                                      if (lines.isNotEmpty && (lines.first == 'php' || lines.first == 'dart')) {
                                        lines.removeAt(0);
                                      }
                                      final cleanCode = lines.join('\n');
                                      codeController.text = cleanCode;
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        const SnackBar(content: Text('File content replaced with code block!')),
                                      );
                                    }
                                  }
                                },
                                icon: const Icon(Icons.file_copy_outlined, size: 12),
                                label: const Text('Replace Active File', style: TextStyle(fontSize: 10)),
                              ),
                            ],
                          ),
                        )
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        if (isLoading)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 4.0),
            child: LinearProgressIndicator(),
          ),
        Container(
          padding: const EdgeInsets.all(12),
          color: const Color(0xFF0F172A),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: chatInputController,
                  maxLines: null,
                  decoration: const InputDecoration(
                    hintText: 'Type message...',
                    border: InputBorder.none,
                    isDense: true,
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.send, color: Color(0xFF8B5CF6)),
                onPressed: _sendChatMessage,
              ),
            ],
          ),
        ),
      ],
    );

    final settingsWidget = SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('CONFIGURATION', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF8B5CF6))),
          TextField(
            onChanged: (val) {
              backendUrl = val.trim();
              _fetchServerWorkspaces();
            },
            decoration: const InputDecoration(
              labelText: 'PHP Backend URL',
              border: OutlineInputBorder(),
              helperText: 'e.g. http://localhost:8000 or staging server ip',
            ),
            controller: TextEditingController(text: backendUrl),
          ),
          const SizedBox(height: 16),
          const Text('Active Workspace Project', style: TextStyle(fontSize: 12, color: Colors.grey)),
          const SizedBox(height: 8),
          serverWorkspaces.isEmpty
              ? const Text('No uploaded projects found on server. Upload a folder via Web IDE first, or check your Backend URL.', style: TextStyle(color: Colors.redAccent, fontSize: 12))
              : Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white24),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      value: serverWorkspaces.any((w) => w['path'] == activeWorkspacePath) ? activeWorkspacePath : null,
                      hint: const Text('Select a workspace...'),
                      isExpanded: true,
                      items: serverWorkspaces.map<DropdownMenuItem<String>>((workspace) {
                        return DropdownMenuItem<String>(
                          value: workspace['path'] as String,
                          child: Text(workspace['name'] as String),
                        );
                      }).toList(),
                      onChanged: (val) {
                        if (val != null) {
                          setState(() {
                            activeWorkspacePath = val;
                          });
                        }
                      },
                    ),
                  ),
                ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton.icon(
                onPressed: _fetchServerWorkspaces,
                icon: const Icon(Icons.refresh, size: 16),
                label: const Text('Refresh Workspaces List', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          TextField(
            onChanged: (val) => geminiKey = val.trim(),
            obscureText: true,
            decoration: const InputDecoration(
              labelText: 'Google Gemini API Key (AI Studio)',
              border: OutlineInputBorder(),
            ),
            controller: TextEditingController(text: geminiKey),
          ),
          const SizedBox(height: 16),
          TextField(
            onChanged: (val) => openaiKey = val.trim(),
            obscureText: true,
            decoration: const InputDecoration(
              labelText: 'OpenAI ChatGPT API Key',
              border: OutlineInputBorder(),
            ),
            controller: TextEditingController(text: openaiKey),
          ),
          const SizedBox(height: 16),
          TextField(
            onChanged: (val) => claudeKey = val.trim(),
            obscureText: true,
            decoration: const InputDecoration(
              labelText: 'Anthropic Claude API Key',
              border: OutlineInputBorder(),
            ),
            controller: TextEditingController(text: claudeKey),
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            height: 45,
            child: ElevatedButton(
              onPressed: _saveSettings,
              child: const Text('Save Settings'),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Keys and workspace paths are stored locally on this device via SharedPreferences and sent as request headers to your proxy server backend.',
            style: TextStyle(fontSize: 11, color: Colors.grey),
          ),
        ],
      ),
    );

    // Responsive builds
    if (isDesktop) {
      return Scaffold(
        appBar: AppBar(
          title: Row(
            children: [
              const Icon(Icons.auto_awesome, color: Color(0xFF8B5CF6)),
              const SizedBox(width: 8),
              const Text('Internal Cursor IDE', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(width: 16),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: Colors.green.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: const Text('PC WEB/DESKTOP MODE', style: TextStyle(color: Colors.green, fontSize: 10, fontWeight: FontWeight.bold)),
              )
            ],
          ),
          elevation: 1,
          actions: [
            IconButton(
              icon: const Icon(Icons.settings),
              onPressed: () {
                showModalBottomSheet(
                  context: context,
                  isScrollControlled: true,
                  builder: (ctx) => Padding(
                    padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
                    child: SizedBox(height: 520, child: settingsWidget),
                  ),
                );
              },
            )
          ],
        ),
        body: Row(
          children: [
            // Left sidebar: explorer
            SizedBox(width: 250, child: explorerWidget),
            const VerticalDivider(width: 1, color: Colors.white10),
            // Center area: editor
            Expanded(child: editorWidget),
            const VerticalDivider(width: 1, color: Colors.white10),
            // Right sidebar: chat
            SizedBox(width: 320, child: chatWidget),
          ],
        ),
      );
    } else {
      // Mobile screen: bottom bar tabs
      final List<Widget> mobileTabs = [
        explorerWidget,
        editorWidget,
        chatWidget,
        settingsWidget,
      ];

      return Scaffold(
        appBar: AppBar(
          title: const Text('Internal Cursor Mobile'),
          actions: [
            if (mobileIndex == 1 && activeFilePath != null)
              IconButton(
                icon: const Icon(Icons.save),
                onPressed: _saveFile,
              )
          ],
        ),
        body: mobileTabs[mobileIndex],
        bottomNavigationBar: BottomNavigationBar(
          currentIndex: mobileIndex,
          type: BottomNavigationBarType.fixed,
          onTap: (index) {
            setState(() {
              mobileIndex = index;
            });
          },
          items: const [
            BottomNavigationBarItem(icon: Icon(Icons.folder_open), label: 'Explorer'),
            BottomNavigationBarItem(icon: Icon(Icons.edit_note), label: 'Editor'),
            BottomNavigationBarItem(icon: Icon(Icons.chat_bubble_outline), label: 'AI Chat'),
            BottomNavigationBarItem(icon: Icon(Icons.settings), label: 'Settings'),
          ],
        ),
      );
    }
  }
}

// Custom Row widget wrapper to support cross-compile naming in older flutter dependencies
class MainbarAxisAlignment {
  static const spaceBetween = MainAxisAlignment.spaceBetween;
}
