#!/usr/bin/env python3
# Update showLandmarkDetails for unified layout

with open('index.html', 'r') as f:
    html = f.read()

# Find and replace the showLandmarkDetails function
old_function_start = '''        function showLandmarkDetails(landmark) {
            selectedLandmark = landmark;
            
            document.getElementById('categoryBadge').textContent = landmark.category;
            document.getElementById('categoryBadge').style.background = categoryColors[landmark.category];
            document.getElementById('landmarkTitle').textContent = landmark.name;
            document.getElementById('landmarkDescription').textContent = landmark.description;
            document.getElementById('openingHours').textContent = landmark.openingHours;
            document.getElementById('admissionFee').textContent = landmark.admissionFee;
            document.getElementById('coordinates').textContent = 
                `${landmark.lat.toFixed(4)}, ${landmark.lng.toFixed(4)}`;

            const websiteLink = document.getElementById('websiteLink');
            if (landmark.website) {
                websiteLink.href = landmark.website;
                websiteLink.style.display = 'inline-flex';
            } else {
                websiteLink.style.display = 'none';
            }

            const gallery = document.getElementById('imageGallery');
            gallery.innerHTML = '';
            if (landmark.images && landmark.images.length > 0) {
                landmark.images.forEach((imgCode, idx) => {
                    const placeholder = document.createElement('div');
                    placeholder.className = 'image-placeholder';
                    placeholder.style.backgroundImage = `url('assets/images/${imgCode}.webp')`;
                    placeholder.title = `${imgCode}.png`;
                    gallery.appendChild(placeholder);
                });
            }

            const tab3d = document.getElementById('tab3d');
            if (landmark.hasGaussianSplat) {
                tab3d.style.display = 'block';
                document.getElementById('splatFilename').textContent = landmark.splatFile;
            } else {
                tab3d.style.display = 'none';
            }

            // Setup audio tab
            const tabAudio = document.getElementById('tabAudio');
            if (landmark.audioUrl) {
                tabAudio.style.display = 'block';
                const audioContainer = document.getElementById('audioPlayerContainer');
                if (window.AudioPlayer) {
                    audioContainer.innerHTML = window.AudioPlayer.create(landmark);
                }
            } else {
                tabAudio.style.display = 'none';
            }

            // Track analytics
            if (window.Analytics) {
                window.Analytics.trackLandmarkViewed(landmark);
            }

            updateMarkerStyles();
            document.getElementById('landmarkModal').classList.add('show');
        }'''

new_function = '''        function showLandmarkDetails(landmark) {
            selectedLandmark = landmark;
            
            // Clear any previous viewer
            closeViewer();
            
            // Update header
            document.getElementById('categoryBadge').textContent = landmark.category;
            document.getElementById('categoryBadge').style.background = categoryColors[landmark.category];
            document.getElementById('landmarkTitle').textContent = landmark.name;
            document.getElementById('landmarkDescription').textContent = landmark.description;
            
            // Update visit info
            document.getElementById('openingHours').textContent = landmark.openingHours;
            document.getElementById('admissionFee').textContent = landmark.admissionFee;

            // Website link
            const websiteLink = document.getElementById('websiteLink');
            if (landmark.website) {
                websiteLink.href = landmark.website;
                websiteLink.style.display = 'inline-flex';
            } else {
                websiteLink.style.display = 'none';
            }

            // Show 3D model OR images (mutually exclusive)
            const viewer3dContainer = document.getElementById('viewer3dContainer');
            const gallery = document.getElementById('imageGallery');
            
            if (landmark.hasGaussianSplat) {
                // Show 3D model
                viewer3dContainer.style.display = 'block';
                gallery.style.display = 'none';
                
                // Load 3D model immediately
                setTimeout(() => load3DModel(), 100);
            } else {
                // Show image gallery
                viewer3dContainer.style.display = 'none';
                gallery.style.display = 'flex';
                
                gallery.innerHTML = '';
                if (landmark.images && landmark.images.length > 0) {
                    landmark.images.forEach((imgCode) => {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'image-placeholder';
                        placeholder.style.backgroundImage = `url('assets/images/${imgCode}.webp')`;
                        gallery.appendChild(placeholder);
                    });
                }
            }

            // Audio player (always show if available)
            const audioContainer = document.getElementById('audioPlayerContainer');
            if (landmark.audioUrl) {
                audioContainer.style.display = 'block';
                if (window.AudioPlayer) {
                    audioContainer.innerHTML = window.AudioPlayer.create(landmark);
                }
            } else {
                audioContainer.style.display = 'none';
            }

            // Track analytics
            if (window.Analytics) {
                window.Analytics.trackLandmarkViewed(landmark);
            }

            updateMarkerStyles();
            document.getElementById('landmarkModal').classList.add('show');
        }'''

html = html.replace(old_function_start, new_function)

with open('index.html', 'w') as f:
    f.write(html)

print("✅ showLandmarkDetails updated for unified layout")
