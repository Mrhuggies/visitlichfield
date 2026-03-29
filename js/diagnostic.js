// Quick diagnostic for 3D viewer issues
console.log('=== 3D VIEWER DIAGNOSTIC ===');
console.log('THREE.js loaded:', typeof window.THREE !== 'undefined');
console.log('LumaSplatsThree loaded:', typeof window.LumaSplatsThree !== 'undefined');
console.log('PLYLoader loaded:', typeof window.PLYLoader !== 'undefined');

// Test if we can create a scene
try {
    const testScene = new THREE.Scene();
    console.log('✅ THREE.Scene works');
} catch(e) {
    console.error('❌ THREE.Scene error:', e);
}

// Check if splat files exist
const splatFiles = ['kiosk.ply', 'phonebox.ply', 'tate.ply'];
splatFiles.forEach(file => {
    fetch(`assets/splats/${file}`, {method: 'HEAD'})
        .then(r => console.log(`${r.ok ? '✅' : '❌'} ${file} - Status: ${r.status}`))
        .catch(e => console.error(`❌ ${file} - Error:`, e.message));
});

console.log('=== END DIAGNOSTIC ===');
