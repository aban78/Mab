import 'dart:convert';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:google_fonts/google_fonts.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'InspireMe - Daily Quotes',
      debugShowCheckedModeBanner: false,
      theme: ThemeData.dark().copyWith(
        scaffoldBackgroundColor: const Color(0xFF0B0F19),
        colorScheme: const ColorScheme.dark(
          primary: Color(0xFF6366F1), // Indigo
          secondary: Color(0xFFEC4899), // Pink
          surface: Color(0xFF1E293B),
          background: Color(0xFF0B0F19),
        ),
      ),
      home: const QuoteScreen(),
    );
  }
}

class QuoteScreen extends StatefulWidget {
  const QuoteScreen({super.key});

  @override
  State<QuoteScreen> createState() => _QuoteScreenState();
}

class _QuoteScreenState extends State<QuoteScreen> {
  String _currentQuote = "Click the button below to get inspired.";
  String _currentAuthor = "InspireMe";
  bool _isLoading = false;
  String? _errorMessage;

  // Local fallback quotes in case of API failure or offline mode
  final List<Map<String, String>> _fallbackQuotes = [
    {
      "content": "The only way to do great work is to love what you do.",
      "author": "Steve Jobs"
    },
    {
      "content": "Believe you can and you're halfway there.",
      "author": "Theodore Roosevelt"
    },
    {
      "content": "It does not matter how slowly you go as long as you do not stop.",
      "author": "Confucius"
    },
    {
      "content": "Act as if what you do makes a difference. It does.",
      "author": "William James"
    },
    {
      "content": "Success is not final, failure is not fatal: it is the courage to continue that counts.",
      "author": "Winston Churchill"
    },
    {
      "content": "The future belongs to those who believe in the beauty of their dreams.",
      "author": "Eleanor Roosevelt"
    },
    {
      "content": "You miss 100% of the shots you don't take.",
      "author": "Wayne Gretzky"
    }
  ];

  @override
  void initState() {
    super.initState();
    _fetchQuote();
  }

  Future<void> _fetchQuote() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      // Fetch from a stable, active public quotes API (dummyjson.com)
      final response = await http
          .get(Uri.parse('https://dummyjson.com/quotes/random'))
          .timeout(const Duration(seconds: 5));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        setState(() {
          _currentQuote = data['quote'] ?? 'No quote content found.';
          _currentAuthor = data['author'] ?? 'Unknown';
          _isLoading = false;
        });
      } else {
        throw Exception('Failed to load quote (Status: ${response.statusCode})');
      }
    } catch (e) {
      // Fallback to a random local quote in case of network/API error
      final random = Random();
      final randomQuote = _fallbackQuotes[random.nextInt(_fallbackQuotes.length)];
      
      setState(() {
        _currentQuote = randomQuote['content']!;
        _currentAuthor = randomQuote['author']!;
        _errorMessage = "Could not connect to the live API. Showing an offline quote.";
        _isLoading = false;
      });
    }
  }

  void _copyToClipboard() {
    Clipboard.setData(ClipboardData(text: '"$_currentQuote" — $_currentAuthor'));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Quote copied to clipboard!'),
        backgroundColor: Theme.of(context).colorScheme.secondary,
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF0F172A), // Deep Slate
              Color(0xFF020617), // Very Dark Blue
              Color(0xFF1E1B4B), // Deep Indigo tint
            ],
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Header
                Text(
                  'INSPIRE ME',
                  style: GoogleFonts.outfit(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 4.0,
                    color: Colors.white.withOpacity(0.9),
                  ),
                ),

                // Quote Card Area
                Expanded(
                  child: Center(
                    child: SingleChildScrollView(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          // Card wrapper with premium styling
                          Container(
                            width: double.infinity,
                            constraints: const BoxConstraints(maxWidth: 500),
                            decoration: BoxDecoration(
                              color: const Color(0xFF1E293B).withOpacity(0.4),
                              borderRadius: BorderRadius.circular(24.0),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.1),
                                width: 1.0,
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withOpacity(0.25),
                                  blurRadius: 20,
                                  offset: const Offset(0, 10),
                                ),
                              ],
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(32.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Styled Opening Quote Mark
                                  Text(
                                    '“',
                                    style: GoogleFonts.playfairDisplay(
                                      fontSize: 72,
                                      fontWeight: FontWeight.w900,
                                      color: Theme.of(context).colorScheme.primary.withOpacity(0.4),
                                      height: 0.5,
                                    ),
                                  ),
                                  
                                  const SizedBox(height: 8),

                                  // Quote Text
                                  AnimatedSwitcher(
                                    duration: const Duration(milliseconds: 300),
                                    child: _isLoading
                                        ? const Center(
                                            child: Padding(
                                              padding: EdgeInsets.symmetric(vertical: 24.0),
                                              child: CircularProgressIndicator(),
                                            ),
                                          )
                                        : Column(
                                            key: ValueKey<String>(_currentQuote),
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                _currentQuote,
                                                style: GoogleFonts.merriweather(
                                                  fontSize: 22,
                                                  fontWeight: FontWeight.w300,
                                                  height: 1.6,
                                                  color: Colors.white.withOpacity(0.95),
                                                ),
                                              ),
                                              const SizedBox(height: 24),
                                              // Author Row
                                              Row(
                                                mainAxisAlignment: MainAxisAlignment.end,
                                                children: [
                                                  Container(
                                                    width: 24,
                                                    height: 1,
                                                    color: Theme.of(context).colorScheme.secondary,
                                                  ),
                                                  const SizedBox(width: 8),
                                                  Text(
                                                    '- $_currentAuthor',
                                                    style: GoogleFonts.lato(
                                                      fontSize: 16,
                                                      fontWeight: FontWeight.w600,
                                                      fontStyle: FontStyle.italic,
                                                      color: Theme.of(context).colorScheme.secondary,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ],
                                          ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                          
                          if (_errorMessage != null && !_isLoading) ...[
                            const SizedBox(height: 16),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              decoration: BoxDecoration(
                                color: Colors.amber.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: Colors.amber.withOpacity(0.3)),
                              ),
                              child: Text(
                                _errorMessage!,
                                style: GoogleFonts.lato(
                                  fontSize: 12,
                                  color: Colors.amber,
                                ),
                                textAlign: TextAlign.center,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                ),

                // Action Buttons
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Copy and Refresh buttons
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        // Copy Button
                        IconButton(
                          onPressed: _isLoading ? null : _copyToClipboard,
                          icon: const Icon(Icons.copy_rounded),
                          color: Colors.white.withOpacity(0.7),
                          iconSize: 24,
                          tooltip: 'Copy Quote',
                        ),
                        const SizedBox(width: 24),
                        // Refresh Button
                        ElevatedButton(
                          onPressed: _isLoading ? null : _fetchQuote,
                          style: ElevatedButton.styleFrom(
                            foregroundColor: Colors.white,
                            backgroundColor: Theme.of(context).colorScheme.primary,
                            padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(30),
                            ),
                            elevation: 4,
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.refresh_rounded),
                              const SizedBox(width: 8),
                              Text(
                                'New Quote',
                                style: GoogleFonts.lato(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
