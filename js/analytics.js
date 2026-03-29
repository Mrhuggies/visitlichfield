// Google Analytics 4 Integration
(function() {
    // Wait for AppConfig to be loaded
    function initAnalytics() {
        if (!window.AppConfig || !window.AppConfig.GA4_ID) {
            console.log('⏳ Waiting for AppConfig...');
            return;
        }
        
        // Load GA4 script
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${window.AppConfig.GA4_ID}`;
        document.head.appendChild(script);
        
        // Initialize GA4
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', window.AppConfig.GA4_ID, {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=None;Secure'
        });
        
        // Make gtag globally available
        window.gtag = gtag;
        console.log('📊 Analytics initialized with GA4 ID:', window.AppConfig.GA4_ID);
    }
    
    // Try to init immediately, or wait for config
    if (window.AppConfig && window.AppConfig.GA4_ID) {
        initAnalytics();
    } else {
        window.addEventListener('cityConfigLoaded', initAnalytics);
    }
    
    // Track custom events - with safety checks
    window.Analytics = {
        trackRouteSelected: function(route) {
            if (!window.gtag) return;
            gtag('event', 'route_selected', {
                'route_name': route.name,
                'route_difficulty': route.difficulty,
                'route_duration': route.duration,
                'route_distance': route.distance
            });
        },
        
        trackLandmarkViewed: function(landmark) {
            if (!window.gtag) return;
            gtag('event', 'landmark_viewed', {
                'landmark_name': landmark.name,
                'landmark_category': landmark.category,
                'has_3d_model': landmark.hasGaussianSplat,
                'has_audio': landmark.audioUrl !== null
            });
        },
        
        trackModelViewed: function(landmark) {
            if (!window.gtag) return;
            gtag('event', 'model_viewed', {
                'landmark': landmark.name,
                'model_file': landmark.splatFile
            });
        },
        
        trackModelLoaded: function(landmark, loadTime) {
            if (!window.gtag) return;
            gtag('event', 'model_loaded', {
                'landmark': landmark.name,
                'load_time_ms': loadTime
            });
        },
        
        trackNavigationOpened: function(app, route) {
            if (!window.gtag) return;
            gtag('event', 'navigation_opened', {
                'app': app,
                'route': route ? route.name : 'landmark'
            });
        },
        
        trackAudioPlayed: function(landmark) {
            if (!window.gtag) return;
            gtag('event', 'audio_played', {
                'landmark': landmark.name,
                'audio_duration': landmark.audioDuration
            });
        },
        
        trackAudioCompleted: function(landmark) {
            if (!window.gtag) return;
            gtag('event', 'audio_completed', {
                'landmark': landmark.name
            });
        },
        
        trackLocationEnabled: function() {
            if (!window.gtag) return;
            gtag('event', 'location_enabled', {
                'event_category': 'engagement'
            });
        },
        
        trackLocationDisabled: function() {
            if (!window.gtag) return;
            gtag('event', 'location_disabled', {
                'event_category': 'engagement'
            });
        },
        
        trackARNavigationStarted: function(landmark) {
            if (!window.gtag) return;
            gtag('event', 'ar_navigation_started', {
                'landmark': landmark.name
            });
        },
        
        trackARNavigationCompleted: function(landmark) {
            if (!window.gtag) return;
            gtag('event', 'ar_navigation_completed', {
                'landmark': landmark.name
            });
        }
    };
    
    console.log('📊 Analytics module loaded');
})();
