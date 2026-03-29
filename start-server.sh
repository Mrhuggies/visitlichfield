#!/bin/bash
cd "/Users/thomashughes/Desktop/Lichfield Tour"
echo "🚀 Starting local server at http://localhost:8000"
echo "📂 Serving: $(pwd)"
echo ""
echo "Press Ctrl+C to stop"
echo ""
python3 -m http.server 8000
