// User Location Tracking
window.LocationTracker = (function() {
    let userLocationMarker = null;
    let userLocationCircle = null;
    let watchId = null;
    let userLocation = null;
    let isTracking = false;
    
    function enableUserLocation(map) {
        if (!navigator.geolocation) {
            alert('Geolocation not supported by your browser');
            return false;
        }
        
        // Request location permission
        navigator.geolocation.getCurrentPosition(
            (position) => {
                showUserLocation(position, map);
                // Track in analytics
                if (window.Analytics) {
                    window.Analytics.trackLocationEnabled();
                }
            },
            handleLocationError,
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
        
        // Watch position continuously
        watchId = navigator.geolocation.watchPosition(
            (position) => updateUserLocation(position, map),
            handleLocationError,
            {
                enableHighAccuracy: true,
                maximumAge: 1000,
                timeout: 5000
            }
        );
        
        isTracking = true;
        return true;
    }
    
    function showUserLocation(position, map) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy;
        
        userLocation = { lat, lng };
        
        // Create marker if doesn't exist
        if (!userLocationMarker) {
            const userIcon = L.divIcon({
                className: 'user-location-marker',
                html: `
                    <div style="
                        width: 20px; 
                        height: 20px; 
                        background: #4285F4; 
                        border: 3px solid white;
                        border-radius: 50%;
                        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                    "></div>
                `,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            
            userLocationMarker = L.marker([lat, lng], { icon: userIcon }).addTo(map);
            
            userLocationCircle = L.circle([lat, lng], {
                radius: accuracy,
                color: '#4285F4',
                fillColor: '#4285F4',
                fillOpacity: 0.1,
                weight: 1
            }).addTo(map);
            
            map.setView([lat, lng], 16);
        }
    }
    
    function updateUserLocation(position, map) {
        if (userLocationMarker) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            userLocation = { lat, lng };
            
            userLocationMarker.setLatLng([lat, lng]);
            userLocationCircle.setLatLng([lat, lng]);
            userLocationCircle.setRadius(accuracy);
        }
    }
    
    function handleLocationError(error) {
        let message = '';
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message = "Location access denied. Please enable in browser settings.";
                break;
            case error.POSITION_UNAVAILABLE:
                message = "Location information unavailable.";
                break;
            case error.TIMEOUT:
                message = "Location request timed out.";
                break;
        }
        console.error('Location error:', message);
        alert(message);
    }
    
    function disableUserLocation(map) {
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        
        if (userLocationMarker) {
            map.removeLayer(userLocationMarker);
            map.removeLayer(userLocationCircle);
            userLocationMarker = null;
            userLocationCircle = null;
        }
        
        isTracking = false;
        userLocation = null;
    }
    
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3;
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lon2-lon1) * Math.PI/180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c;
    }
    
    function getUserLocation() {
        return userLocation;
    }
    
    function isLocationTracking() {
        return isTracking;
    }
    
    return {
        enable: enableUserLocation,
        disable: disableUserLocation,
        getLocation: getUserLocation,
        isTracking: isLocationTracking,
        calculateDistance: calculateDistance
    };
})();

console.log('📍 Location tracking loaded');
