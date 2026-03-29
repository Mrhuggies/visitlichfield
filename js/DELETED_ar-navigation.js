// AR Navigation Module
window.ARNavigation = (function() {
    let isARActive = false;
    let arInterval = null;
    let targetLandmark = null;
    let userHeading = 0;
    let orientationEnabled = false;
    
    function startARNavigation(landmark, map) {
        if (!window.LocationTracker.isTracking()) {
            alert('Please enable location tracking first!');
            return false;
        }
        
        targetLandmark = landmark;
        isARActive = true;
        
        // Request orientation permission (iOS)
        requestOrientationPermission().then(() => {
            createAROverlay();
            startARTracking();
            
            // Track in analytics
            if (window.Analytics) {
                window.Analytics.trackARNavigationStarted(landmark);
            }
        });
        
        return true;
    }
    
    async function requestOrientationPermission() {
        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            try {
                const permission = await DeviceOrientationEvent.requestPermission();
                if (permission === 'granted') {
                    enableOrientation();
                    return true;
                }
            } catch (error) {
                console.error('Orientation permission error:', error);
            }
        } else {
            enableOrientation();
            return true;
        }
        return false;
    }
    
    function enableOrientation() {
        window.addEventListener('deviceorientation', handleOrientation);
        orientationEnabled = true;
    }
    
    function handleOrientation(event) {
        let heading = event.webkitCompassHeading || event.alpha;
        if (heading !== null) {
            userHeading = heading;
        }
    }
    
    function createAROverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'arOverlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
        `;
        
        overlay.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <div id="arLandmarkName" style="font-size: 24px; font-weight: bold; margin-bottom: 20px;">
                    ${targetLandmark.name}
                </div>
                <div id="arDirection" style="font-size: 48px; margin: 40px 0;">
                    ↑
                </div>
                <div id="arDistance" style="font-size: 32px; margin: 20px 0;">
                    Calculating...
                </div>
                <div style="font-size: 14px; color: #999; margin-top: 40px;">
                    Follow the arrow to your destination
                </div>
                <button onclick="window.ARNavigation.stop()" style="
                    margin-top: 40px;
                    padding: 12px 24px;
                    background: #B8A479;
                    border: none;
                    border-radius: 8px;
                    color: white;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                ">
                    Exit AR Navigation
                </button>
            </div>
        `;
        
        document.body.appendChild(overlay);
    }
    
    function startARTracking() {
        arInterval = setInterval(() => {
            updateARDisplay();
        }, 100);
    }
    
    function updateARDisplay() {
        const userLoc = window.LocationTracker.getLocation();
        if (!userLoc || !targetLandmark) return;
        
        const distance = window.LocationTracker.calculateDistance(
            userLoc.lat, userLoc.lng,
            targetLandmark.lat, targetLandmark.lng
        );
        
        const direction = getDirectionToLandmark(userLoc, targetLandmark);
        
        // Update distance
        const distanceEl = document.getElementById('arDistance');
        if (distanceEl) {
            if (distance < 1000) {
                distanceEl.textContent = `${Math.round(distance)}m ${direction.direction}`;
            } else {
                distanceEl.textContent = `${(distance/1000).toFixed(1)}km ${direction.direction}`;
            }
        }
        
        // Update arrow rotation
        const arrowEl = document.getElementById('arDirection');
        if (arrowEl && orientationEnabled) {
            arrowEl.style.transform = `rotate(${direction.relativeBearing}deg)`;
        }
        
        // Vibrate when close (within 10m)
        if (distance < 10 && navigator.vibrate) {
            navigator.vibrate(200);
            
            // Stop AR and track completion
            setTimeout(() => {
                stopARNavigation();
                if (window.Analytics) {
                    window.Analytics.trackARNavigationCompleted(targetLandmark);
                }
                alert(`You've arrived at ${targetLandmark.name}!`);
            }, 1000);
        }
    }
    
    function getDirectionToLandmark(userLoc, landmark) {
        const lat1 = userLoc.lat * Math.PI / 180;
        const lat2 = landmark.lat * Math.PI / 180;
        const lon1 = userLoc.lng * Math.PI / 180;
        const lon2 = landmark.lng * Math.PI / 180;
        
        const y = Math.sin(lon2 - lon1) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) -
                  Math.sin(lat1) * Math.cos(lat2) * Math.cos(lon2 - lon1);
        const bearing = (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
        
        const relativeBearing = (bearing - userHeading + 360) % 360;
        
        const directions = ['↑ Ahead', '↗ Ahead Right', '→ Right', '↘ Behind Right', 
                           '↓ Behind', '↙ Behind Left', '← Left', '↖ Ahead Left'];
        const index = Math.round(relativeBearing / 45) % 8;
        
        return {
            bearing: bearing,
            relativeBearing: relativeBearing,
            direction: directions[index]
        };
    }
    
    function stopARNavigation() {
        isARActive = false;
        
        if (arInterval) {
            clearInterval(arInterval);
            arInterval = null;
        }
        
        const overlay = document.getElementById('arOverlay');
        if (overlay) {
            overlay.remove();
        }
        
        if (orientationEnabled) {
            window.removeEventListener('deviceorientation', handleOrientation);
            orientationEnabled = false;
        }
        
        targetLandmark = null;
    }
    
    return {
        start: startARNavigation,
        stop: stopARNavigation,
        isActive: () => isARActive
    };
})();

console.log('🎯 AR Navigation loaded');
