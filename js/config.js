// Master Configuration - Multi-City Support
// This loads the correct city based on URL parameter, tour API, or localStorage

(function() {
    'use strict';
    
    // Get parameters from URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlCity = urlParams.get('city');
    const urlTourCode = urlParams.get('code');
    
    let selectedCity = urlCity || null;
    
    // Store tour code if provided
    if (urlTourCode) {
        localStorage.setItem('tourCode', urlTourCode);
    }
    
    // Available cities
    window.AVAILABLE_CITIES = {
        lichfield: {
            name: 'Lichfield',
            slug: 'lichfield',
            configFile: 'js/cities/lichfield.js',
            color: '#B92F7B'
        },
        york: {
            name: 'York',
            slug: 'york',
            configFile: 'js/cities/york.js',
            color: '#8B4513'
        }
    };
    
    // If tour code provided BUT no city parameter, fetch from API
    async function initializeCity() {
        if (urlTourCode && !urlCity) {
            console.log('Tour code provided, fetching current city from API...');
            
            try {
                const response = await fetch(`https://apps.tag-art.co.uk/visitlichfield/api/tours.php?code=${urlTourCode}`);
                const data = await response.json();
                
                if (!data.error) {
                    // Use current_city_slug (the city the tour is currently in)
                    const tourCity = data.current_city_slug || data.city_slug;
                    console.log(`Tour ${urlTourCode} is currently in: ${tourCity}`);
                    selectedCity = tourCity;
                } else {
                    console.warn('Tour not found, using default city');
                    selectedCity = 'lichfield'; // fallback
                }
            } catch (error) {
                console.error('Failed to fetch tour city:', error);
                selectedCity = 'lichfield'; // fallback
            }
        }
        
        // If still no city selected, show city selector
        if (!selectedCity || !window.AVAILABLE_CITIES[selectedCity]) {
            window.SHOW_CITY_SELECTOR = true;
            loadCitySelector();
        } else {
            window.SHOW_CITY_SELECTOR = false;
            window.CURRENT_CITY = selectedCity;
            localStorage.setItem('selectedCity', selectedCity);
            
            // Load city config dynamically
            loadCityConfig(selectedCity);
        }
    }
    
    function loadCityConfig(citySlug) {
        const script = document.createElement('script');
        script.src = window.AVAILABLE_CITIES[citySlug].configFile;
        script.onload = function() {
            // Map city config to AppConfig for compatibility
            if (citySlug === 'lichfield') {
                window.AppConfig = window.LichfieldConfig;
            } else if (citySlug === 'york') {
                window.AppConfig = window.YorkConfig;
            }
            
            // Trigger loaded event
            window.dispatchEvent(new Event('cityConfigLoaded'));
        };
        document.head.appendChild(script);
    }
    
    function loadCitySelector() {
        // City selector will be shown by initApp()
    }
    
    // Function to switch cities
    window.switchCity = function(citySlug) {
        const tourCode = localStorage.getItem('tourCode');
        if (tourCode) {
            // Include tour code in URL
            window.location.href = window.location.pathname + '?city=' + citySlug + '&code=' + tourCode;
        } else {
            window.location.href = window.location.pathname + '?city=' + citySlug;
        }
    };
    
    // Initialize
    initializeCity();
    
})();
