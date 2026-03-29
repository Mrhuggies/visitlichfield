# Audio Guide Setup

## Folder Structure

Each landmark has its own folder with audio files:

```
assets/audio/
├── cathedral/
│   ├── guide.mp3          (Main audio guide)
│   └── extended.mp3       (Optional extended version)
├── samuel-johnson/
│   └── guide.mp3
├── erasmus-darwin/
│   └── guide.mp3
├── beacon-park/
│   └── guide.mp3
├── minster-pool/
│   └── guide.mp3
└── market-square/
    └── guide.mp3
```

## Creating Audio Guides

### Recommended Free Text-to-Speech Services:

#### 1. **ElevenLabs** (BEST QUALITY)
- URL: elevenlabs.io
- Free: 10,000 characters/month
- Voices: Ultra-realistic, multiple accents
- Export: MP3
- **Recommended for final production**

**Example Script:**
```
Welcome to Lichfield Cathedral, the only medieval cathedral in Europe 
with three spires, known as the Ladies of the Vale. Dating from 1195, 
this magnificent Gothic structure has stood as a beacon of faith and 
community for over 800 years...
```

#### 2. **Google Cloud Text-to-Speech**
- URL: cloud.google.com/text-to-speech
- Free: 4 million characters/month
- Voices: WaveNet (high quality), Neural2 (premium)
- Languages: 40+ including UK English
- Export: MP3, WAV

**Setup:**
```bash
# Install gcloud CLI
pip install google-cloud-texttospeech

# Create audio
python create_audio.py --text cathedral_script.txt --output cathedral/guide.mp3
```

#### 3. **Amazon Polly**
- URL: aws.amazon.com/polly
- Free: 5 million characters/month (first 12 months)
- Voices: Neural TTS, British English
- Export: MP3, OGG

#### 4. **Murf.ai**
- URL: murf.ai
- Free: 10 minutes of audio
- Voices: 120+ AI voices
- Features: Pause control, emphasis, pitch
- Export: MP3, WAV

#### 5. **Play.ht**
- URL: play.ht
- Free: 2,500 words
- Voices: Ultra-realistic
- Export: MP3

### Audio Script Templates

#### Short Version (2-3 minutes):
- Introduction (20 seconds)
- Main historical fact (60 seconds)
- Interesting story (40 seconds)
- Visitor information (20 seconds)

#### Extended Version (5-8 minutes):
- Detailed history
- Multiple stories/anecdotes
- Architectural details
- Fun facts
- What to look for

### Audio Specifications

**Format:** MP3
**Bitrate:** 128 kbps (good quality, small file)
**Sample Rate:** 44.1 kHz
**Channels:** Mono (smaller file size)

**File Size Estimates:**
- 2 min = ~2 MB
- 5 min = ~5 MB
- 8 min = ~8 MB

### Example Script: Lichfield Cathedral

```
[CATHEDRAL AUDIO GUIDE - 3 MINUTES]

Welcome to Lichfield Cathedral.

You're standing before one of England's most magnificent Gothic 
masterpieces, and the only medieval cathedral in all of Europe with 
three spires. 

These three spires, affectionately known as "the Ladies of the Vale," 
have dominated Lichfield's skyline for over 800 years.

Construction began in 1195, and it took nearly 150 years to complete. 
The cathedral you see today represents the pinnacle of medieval 
craftsmanship and engineering.

During the English Civil War, this cathedral was besieged three times, 
and the central spire was actually destroyed by cannon fire. It was 
rebuilt in the 1660s, taller and more magnificent than before.

Inside, you'll find the Lichfield Angel, a stunning 8th-century 
limestone carving discovered during restoration work. Art historians 
consider it one of the finest examples of Anglo-Saxon sculpture.

The cathedral is also home to the St Chad Gospels, an 8th-century 
illuminated manuscript that's one of the oldest books in England.

Take a moment to walk around the exterior and notice the intricate 
stone carvings. Over 100 statues adorn the West Front, depicting kings, 
queens, and saints.

The cathedral is open daily, and admission is free, though donations 
are gratefully received to help maintain this historic treasure.

Thank you for visiting Lichfield Cathedral.
```

### Recording Your Own Audio

If you prefer human voice-over:

**Equipment Needed:**
- USB microphone (£30-80)
- Quiet room
- Audacity (free software)

**Recording Tips:**
1. Read naturally, as if guiding someone
2. Pause between sentences
3. Emphasize interesting facts
4. Keep energy up throughout
5. Record in 2-3 takes, use best version

### Processing Audio Files

**Using Audacity (Free):**
1. Import your audio
2. Effect → Normalize → OK
3. Effect → Noise Reduction (if needed)
4. File → Export → Export as MP3
5. Settings: 128 kbps, Mono

### Batch Processing Multiple Files

```bash
# Convert all WAV to MP3
for file in *.wav; do
    ffmpeg -i "$file" -b:a 128k -ac 1 "${file%.wav}.mp3"
done
```

## Uploading Audio Files

### Option 1: Local (Development)
1. Save MP3 files to respective landmark folders
2. File must be named `guide.mp3`
3. Refresh browser

### Option 2: Cloud Storage (Production)

**Cloudflare R2 (Recommended):**
- Cost: $0.015/GB/month
- Free: 10GB storage
- No egress fees
- Fast CDN

**Setup:**
1. Create R2 bucket: `lichfield-audio`
2. Upload MP3 files
3. Get public URL
4. Update `audioUrl` in landmarks

**AWS S3:**
- Cost: $0.023/GB/month
- Fast, reliable
- Worldwide CDN

**Google Cloud Storage:**
- Cost: $0.020/GB/month
- Good EU performance

### Updating Landmark Audio URLs

Edit `index.html`, find landmarks array:

```javascript
{
    id: 1,
    name: "Lichfield Cathedral",
    audioUrl: "assets/audio/cathedral/guide.mp3",  // Local
    // OR
    audioUrl: "https://cdn.yourdomain.com/audio/cathedral.mp3",  // CDN
    audioDuration: "3:15",
    ...
}
```

## Testing Audio

1. Open app in browser
2. Click landmark
3. Look for Audio tab (🎧)
4. Click play button
5. Audio should play
6. Check browser console for errors

## File Naming Convention

**Standard:**
- `guide.mp3` - Main audio guide
- `guide-extended.mp3` - Extended version
- `guide-kids.mp3` - Child-friendly version
- `guide-fr.mp3` - French language
- `guide-de.mp3` - German language

## Multi-Language Support

Create folders for each language:

```
assets/audio/
├── en/
│   └── cathedral/
│       └── guide.mp3
├── fr/
│   └── cathedral/
│       └── guide.mp3
└── de/
    └── cathedral/
        └── guide.mp3
```

## Troubleshooting

**Audio doesn't play:**
- Check file path is correct
- Verify MP3 format
- Check browser console for errors
- Test file in VLC/iTunes first

**File too large:**
- Reduce bitrate to 96 kbps
- Convert to mono
- Trim silence at start/end

**Poor quality:**
- Increase bitrate to 192 kbps
- Use better TTS voice
- Record in quieter environment

## Next Steps

1. Write scripts for all 6 landmarks
2. Generate audio using ElevenLabs or Google TTS
3. Save files to appropriate folders
4. Test in browser
5. Deploy to production

## Contact

Questions? Email: hello@tagart.co.uk
