// Audio Player Module
window.AudioPlayer = (function() {
    let currentAudio = null;
    let currentLandmark = null;
    
    function createAudioPlayer(landmark) {
        if (!landmark.audioUrl) {
            return '<p style="text-align: center; padding: 20px;">No audio guide available for this landmark yet.</p>';
        }
        
        // Map landmark names to folder names
        const folderMap = {
            'Lichfield Cathedral': 'cathedral',
            'Samuel Johnson Birthplace': 'samuel-johnson',
            'Beacon Park': 'beacon-park',
            'Erasmus Darwin House': 'erasmus-darwin',
            'Minster Pool': 'minster-pool',
            'Market Square': 'market-square'
        };
        
        const folder = folderMap[landmark.name] || landmark.name.toLowerCase().replace(/ /g, '-');
        const transcriptPath = `assets/audio/${folder}/transcript/SCRIPT.md`;
        
        return `
            <div class="audio-player">
                <div class="audio-info">
                    <div class="audio-title">🎧 Audio Guide</div>
                </div>
                <audio id="audioElement" controls style="width: 100%; margin-top: 10px;">
                    <source src="${landmark.audioUrl}" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
                <button onclick="toggleTranscript('${transcriptPath}')" style="
                    width: 100%;
                    margin-top: 12px;
                    padding: 10px;
                    background: #B92F7B;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                ">
                    📝 Show Transcript
                </button>
                <div id="audioTranscript" style="
                    display: none;
                    margin-top: 12px;
                    padding: 15px;
                    background: #f5f5f5;
                    border-radius: 6px;
                    font-size: 14px;
                    line-height: 1.6;
                    max-height: 300px;
                    overflow-y: auto;
                ">
                    <p style="text-align: center; color: #999;">Loading transcript...</p>
                </div>
                <div class="audio-credits" style="margin-top: 10px; font-size: 11px; color: #999; text-align: center;">
                    Narrated audio guide | TAGart Productions
                </div>
            </div>
        `;
    }
    
    function initAudioPlayer(landmark) {
        currentLandmark = landmark;
        const audioElement = document.getElementById('audioElement');
        
        if (audioElement) {
            // Track play
            audioElement.addEventListener('play', function() {
                if (window.Analytics && currentLandmark) {
                    window.Analytics.trackAudioPlayed(currentLandmark);
                }
            });
            
            // Track completion
            audioElement.addEventListener('ended', function() {
                if (window.Analytics && currentLandmark) {
                    window.Analytics.trackAudioCompleted(currentLandmark);
                }
            });
            
            // Error handling
            audioElement.addEventListener('error', function(e) {
                console.error('Audio error:', e);
                alert('Failed to load audio file. Please check the file exists.');
            });
            
            currentAudio = audioElement;
        }
    }
    
    function stopAudio() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
        }
    }
    
    return {
        create: createAudioPlayer,
        init: initAudioPlayer,
        stop: stopAudio
    };
})();

console.log('🎧 Audio player loaded');
