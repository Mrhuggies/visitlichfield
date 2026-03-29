// Luma AI Gaussian Splat Loader
// This module uses Luma AI's library for proper Gaussian splat rendering with colors

import { LumaSplatsThree } from 'https://unpkg.com/@lumaai/luma-web@0.2.0/dist/library/luma-web.module.js';

window.LumaSplatLoader = (function() {
    let currentSplat = null;
    let loadStartTime = 0;
    
    async function loadSplat(scene, landmark, onProgress) {
        // Clear any existing splat
        if (currentSplat) {
            scene.remove(currentSplat);
            currentSplat = null;
        }
        
        loadStartTime = Date.now();
        
        try {
            // Track model view start
            if (window.Analytics) {
                window.Analytics.trackModelViewed(landmark);
            }
            
            // Create loading message
            if (onProgress) {
                onProgress('Loading Gaussian splat...');
            }
            
            // Create Luma splat
            const splatPath = `assets/splats/${landmark.splatFile}`;
            console.log('Loading splat from:', splatPath);
            
            const splat = new LumaSplatsThree({
                source: splatPath,
                loadingAnimationEnabled: true,
                particleRevealEnabled: true
            });
            
            // Add to scene
            scene.add(splat);
            currentSplat = splat;
            
            // Wait for splat to load
            await new Promise((resolve) => {
                const checkInterval = setInterval(() => {
                    if (splat.loaded) {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
                
                // Timeout after 30 seconds
                setTimeout(() => {
                    clearInterval(checkInterval);
                    resolve();
                }, 30000);
            });
            
            // Calculate load time
            const loadTime = Date.now() - loadStartTime;
            console.log(`Splat loaded in ${loadTime}ms`);
            
            // Track successful load
            if (window.Analytics) {
                window.Analytics.trackModelLoaded(landmark, loadTime);
            }
            
            if (onProgress) {
                onProgress('Splat loaded successfully!');
            }
            
            return splat;
            
        } catch (error) {
            console.error('Error loading splat:', error);
            if (onProgress) {
                onProgress('Error loading 3D model');
            }
            throw error;
        }
    }
    
    function getSplat() {
        return currentSplat;
    }
    
    function removeSplat(scene) {
        if (currentSplat) {
            scene.remove(currentSplat);
            currentSplat = null;
        }
    }
    
    return {
        load: loadSplat,
        get: getSplat,
        remove: removeSplat
    };
})();

console.log('✨ Luma AI Splat Loader initialized');
