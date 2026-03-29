// AR Camera Mode with Real Camera Overlay
window.ARCamera = (function() {
    let stream = null;
    let isActive = false;
    let currentLandmark = null;
    let animationFrame = null;
    
    async function start(landmark) {
        if (!landmark) {
            alert('Please select a landmark first!');
            return;
        }
        
        currentLandmark = landmark;
        
        // Step 1: Check and request location permission
        if (!window.LocationTracker || !window.LocationTracker.isTracking()) {
            const msg = 'AR Navigation needs your location.\n\nPlease tap "Allow" on the location permission popup.';
            alert(msg);
            
            // Enable location tracking
            const btn = document.getElementById('locationBtn');
            if (btn) btn.click();
            
            // Wait for location to be enabled (check every 500ms for up to 15 seconds)
            let attempts = 0;
            const maxAttempts = 30; // 15 seconds
            
            while (attempts < maxAttempts) {
                await new Promise(resolve => setTimeout(resolve, 500));
                
                if (window.LocationTracker && window.LocationTracker.isTracking()) {
                    break; // Location enabled!
                }
                
                attempts++;
            }
            
            // Check if location is now enabled
            if (!window.LocationTracker || !window.LocationTracker.isTracking()) {
                alert('Location permission was not granted.\n\nAR Navigation requires your location to work.');
                return;
            }
        }
        
        // Step 2: Request device orientation permission (iOS)
        if (typeof DeviceOrientationEvent !== 'undefined' && 
            typeof DeviceOrientationEvent.requestPermission === 'function') {
            try {
                const permission = await DeviceOrientationEvent.requestPermission();
                if (permission !== 'granted') {
                    alert('Device orientation permission denied');
                    return;
                }
            } catch (error) {
                console.error('Orientation permission error:', error);
            }
        }
        
        // Step 3: Check if camera permission was previously denied
        if (navigator.permissions && navigator.permissions.query) {
            try {
                const cameraPermission = await navigator.permissions.query({ name: 'camera' });
                console.log('Camera permission state:', cameraPermission.state);
                
                if (cameraPermission.state === 'denied') {
                    alert('Camera access is blocked.\n\nPlease enable camera in your browser settings:\n\n1. Tap the (aA) icon in address bar\n2. Tap "Website Settings"\n3. Enable Camera\n4. Reload the page');
                    return;
                }
            } catch (e) {
                // Permission API not supported, continue anyway
                console.log('Permission API not available:', e);
            }
        }
        
        // Step 4: Alert user before requesting camera
        alert('AR Navigation needs camera access.\n\nPlease tap "Allow" on the next popup to use your camera.');
        
        // Longer delay to ensure alert is dismissed and user is ready
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Step 5: Request camera permission
        try {
            console.log('Requesting camera access...');
            
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment', // Back camera
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            });
            
            console.log('Camera access granted!');
            
            // Step 6: Create AR overlay and start
            createAROverlay();
            
        } catch (error) {
            console.error('Camera access error:', error);
            console.error('Error name:', error.name);
            console.error('Error message:', error.message);
            
            // Check specific error type
            if (error.name === 'NotAllowedError') {
                alert('Camera permission was denied.\n\nTo use AR Navigation, please enable camera access in your browser settings:\n\n1. Tap the (aA) icon in the address bar\n2. Tap "Website Settings"\n3. Enable Camera');
            } else if (error.name === 'NotFoundError') {
                alert('No camera found on this device.');
            } else {
                alert('Could not access camera: ' + error.message);
            }
        }
    }
    
    function createAROverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'arCameraOverlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10000;
            background: black;
        `;
        
        overlay.innerHTML = `
            <video id="arVideo" autoplay playsinline muted style="
                width: 100%;
                height: 100%;
                object-fit: cover;
            "></video>
            
            <div id="arOverlayContent" style="
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                pointer-events: none;
            ">
                <div style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                ">
                    <div id="arArrow" style="
                        font-size: 80px;
                        color: #B92F7B;
                        text-shadow: 0 0 20px rgba(185,47,123,0.8),
                                   0 0 40px rgba(185,47,123,0.6);
                        margin-bottom: 20px;
                        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.9));
                        transition: transform 0.3s ease;
                    ">
                        ↑
                    </div>
                </div>
                
                <div style="
                    position: absolute;
                    top: 40px;
                    left: 0;
                    right: 0;
                    text-align: center;
                ">
                    <div style="
                        display: inline-block;
                        background: rgba(0, 0, 0, 0.8);
                        padding: 15px 30px;
                        border-radius: 15px;
                        border: 2px solid #B92F7B;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
                    ">
                        <div style="
                            color: white;
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 5px;
                        ">
                            ${currentLandmark.name}
                        </div>
                        <div id="arDistance" style="
                            color: #B92F7B;
                            font-size: 32px;
                            font-weight: bold;
                        ">
                            ...
                        </div>
                        <div id="arDirection" style="
                            color: #ccc;
                            font-size: 16px;
                            margin-top: 5px;
                        ">
                            ...
                        </div>
                    </div>
                </div>
            </div>
            
            <button onclick="window.ARCamera.stop()" style="
                position: absolute;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                padding: 15px 40px;
                background: #B92F7B;
                border: none;
                border-radius: 30px;
                color: white;
                font-size: 18px;
                font-weight: 600;
                box-shadow: 0 4px 15px rgba(0,0,0,0.5);
                cursor: pointer;
                pointer-events: all;
                z-index: 10001;
            ">
                Exit AR Camera
            </button>
        `;
        
        document.body.appendChild(overlay);
        
        const video = document.getElementById('arVideo');
        video.srcObject = stream;
        
        isActive = true;
        
        // Start device orientation listener
        window.addEventListener('deviceorientation', handleOrientation);
        
        // Start AR update loop
        updateAR();
        
        // Track analytics
        if (window.Analytics) {
            window.Analytics.trackARNavigationStarted(currentLandmark);
        }
    }
    
    let currentHeading = 0;
    
    function handleOrientation(event) {
        if (!isActive) return;
        
        // Get compass heading
        let heading = event.webkitCompassHeading || event.alpha || 0;
        currentHeading = heading;
    }
    
    function updateAR() {
        if (!isActive) return;
        
        const userLoc = window.LocationTracker.getLocation();
        if (!userLoc) {
            animationFrame = requestAnimationFrame(updateAR);
            return;
        }
        
        // Calculate distance
        const distance = window.LocationTracker.calculateDistance(
            userLoc.lat, userLoc.lng,
            currentLandmark.lat, currentLandmark.lng
        );
        
        // Calculate bearing to landmark
        const bearing = calculateBearing(
            userLoc.lat, userLoc.lng,
            currentLandmark.lat, currentLandmark.lng
        );
        
        // Calculate relative bearing (direction from where user is facing)
        let relativeBearing = bearing - currentHeading;
        if (relativeBearing < 0) relativeBearing += 360;
        if (relativeBearing > 360) relativeBearing -= 360;
        
        // Update UI
        const distanceEl = document.getElementById('arDistance');
        const directionEl = document.getElementById('arDirection');
        const arrowEl = document.getElementById('arArrow');
        
        if (distanceEl) {
            distanceEl.textContent = distance < 1000 
                ? `${Math.round(distance)}m` 
                : `${(distance / 1000).toFixed(1)}km`;
        }
        
        if (directionEl) {
            directionEl.textContent = getDirectionText(relativeBearing);
        }
        
        if (arrowEl) {
            // Rotate arrow based on relative bearing
            let arrowRotation = relativeBearing;
            arrowEl.style.transform = `rotate(${arrowRotation}deg)`;
            
            // Change arrow color based on direction accuracy
            if (Math.abs(relativeBearing) < 15 || Math.abs(relativeBearing - 360) < 15) {
                arrowEl.style.color = '#4CAF50'; // Green when pointing right way
            } else {
                arrowEl.style.color = '#B92F7B'; // Pink otherwise
            }
        }
        
        // Vibrate when close
        if (distance < 10 && navigator.vibrate) {
            navigator.vibrate(200);
            stop();
            alert(`You've arrived at ${currentLandmark.name}!`);
            if (window.Analytics) {
                window.Analytics.trackARNavigationCompleted(currentLandmark);
            }
            return;
        }
        
        animationFrame = requestAnimationFrame(updateAR);
    }
    
    function calculateBearing(lat1, lng1, lat2, lng2) {
        const dLon = (lng2 - lng1) * Math.PI / 180;
        lat1 = lat1 * Math.PI / 180;
        lat2 = lat2 * Math.PI / 180;
        
        const y = Math.sin(dLon) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) -
                  Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLon);
        
        let bearing = Math.atan2(y, x) * 180 / Math.PI;
        bearing = (bearing + 360) % 360;
        
        return bearing;
    }
    
    function getDirectionText(bearing) {
        if (bearing < 22.5 || bearing >= 337.5) return '↑ Straight Ahead';
        if (bearing < 67.5) return '↗ Ahead Right';
        if (bearing < 112.5) return '→ Turn Right';
        if (bearing < 157.5) return '↘ Behind Right';
        if (bearing < 202.5) return '↓ Turn Around';
        if (bearing < 247.5) return '↙ Behind Left';
        if (bearing < 292.5) return '← Turn Left';
        return '↖ Ahead Left';
    }
    
    function stop() {
        isActive = false;
        
        if (animationFrame) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
        
        window.removeEventListener('deviceorientation', handleOrientation);
        
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        
        const overlay = document.getElementById('arCameraOverlay');
        if (overlay) {
            overlay.remove();
        }
        
        currentLandmark = null;
    }
    
    return {
        start: start,
        stop: stop
    };
})();

console.log('📹 AR Camera module loaded');
