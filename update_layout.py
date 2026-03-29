#!/usr/bin/env python3
# Redesign landmark panel - remove tabs, unified single view

with open('index.html', 'r') as f:
    html = f.read()

# Remove tabs section
old_tabs = '''            <div class="tabs">
                <button class="tab active" onclick="switchTab(event, 'info')">Information</button>
                <button class="tab" onclick="switchTab(event, 'visit')">Visit</button>
                <button class="tab" id="tabAudio" onclick="switchTab(event, 'audio')" style="display:none;">🎧 Audio</button>
                <button class="tab" id="tab3d" onclick="switchTab(event, '3d')" style="display:none;">3D Model</button>
            </div>'''

new_tabs = '''            <!-- Tabs removed - single unified view -->'''

html = html.replace(old_tabs, new_tabs)

# Replace panel content structure
old_content_start = '''            <div class="panel-content">
                <div id="infoTab" class="tab-content active">
                    <p id="landmarkDescription" class="description"></p>
                    <div id="imageGallery" class="image-gallery"></div>
                    <a id="websiteLink" class="website-link" target="_blank" rel="noopener noreferrer" style="display:none;">
                        🌐 Visit Website
                    </a>
                </div>

                <div id="visitTab" class="tab-content">
                    <div class="info-block">
                        <div class="info-label">Opening Hours</div>
                        <div id="openingHours" class="info-value"></div>
                    </div>
                    <div class="info-block">
                        <div class="info-label">Admission</div>
                        <div id="admissionFee" class="info-value"></div>
                    </div>
                    <button class="navigate-button" onclick="navigateToLandmark()">
                        <span>📍</span>
                        Get Directions
                    </button>
                    <button class="navigate-button" onclick="startARNavigation()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin-top: 10px;">
                        <span>🎯</span>
                        AR Navigate
                    </button>
                    <div id="coordinates" class="coordinates"></div>
                </div>

                <div id="audioTab" class="tab-content">
                    <div id="audioPlayerContainer"></div>
                </div>

                <div id="3dTab" class="tab-content">
                    <div class="viewer-3d-container">
                        <div id="viewer3dPlaceholder" class="viewer-placeholder">
                            <div class="viewer-3d-icon">🎭</div>
                            <p>Loading 3D Model...</p>
                            <div id="splatFilename" class="viewer-3d-filename"></div>
                            <div class="loading-spinner"></div>
                            <div class="powered-by">TAGart Photogrammetry</div>
                        </div>
                        <div id="viewer3d" style="display:none;"></div>
                        <div id="viewerControls" class="viewer-controls" style="display:none;">
                            <button class="viewer-button" onclick="resetCamera()">Reset View</button>
                            <button class="viewer-button" onclick="toggleAutoRotate()">Auto Rotate</button>
                            <button class="viewer-button" onclick="closeViewer()">Close</button>
                        </div>
                    </div>
                </div>
            </div>'''

new_content = '''            <div class="panel-content">
                <!-- Description -->
                <p id="landmarkDescription" class="description"></p>

                <!-- 3D Model OR Image Gallery -->
                <div id="viewer3dContainer" class="viewer-3d-container" style="display:none;">
                    <div id="viewer3dPlaceholder" class="viewer-placeholder">
                        <div class="viewer-3d-icon">🎭</div>
                        <p>Loading 3D Model...</p>
                        <div class="loading-spinner"></div>
                        <div class="powered-by">TAGart Photogrammetry</div>
                    </div>
                    <div id="viewer3d"></div>
                    <div id="viewerControls" class="viewer-controls" style="display:none;">
                        <button class="viewer-button" onclick="resetCamera()">🔄 Reset View</button>
                        <button class="viewer-button" onclick="toggleAutoRotate()">🔁 Auto Rotate</button>
                    </div>
                </div>

                <div id="imageGallery" class="image-gallery"></div>

                <!-- Audio Player -->
                <div id="audioPlayerContainer" style="margin-top: 20px;"></div>

                <!-- Visit Info -->
                <div class="visit-section">
                    <h3 style="color: #B92F7B; margin-top: 20px; margin-bottom: 10px;">Visit Information</h3>
                    <div class="info-block">
                        <div class="info-label">Opening Hours</div>
                        <div id="openingHours" class="info-value"></div>
                    </div>
                    <div class="info-block">
                        <div class="info-label">Admission</div>
                        <div id="admissionFee" class="info-value"></div>
                    </div>
                    
                    <a id="websiteLink" class="website-link" target="_blank" rel="noopener noreferrer" style="display:none;">
                        🌐 Visit Website
                    </a>

                    <button class="navigate-button" onclick="navigateToLandmark()">
                        <span>📍</span>
                        Get Directions
                    </button>
                    <button class="navigate-button" onclick="startARNavigation()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin-top: 10px;">
                        <span>🎯</span>
                        AR Navigate
                    </button>
                </div>
            </div>'''

html = html.replace(old_content_start, new_content)

with open('index.html', 'w') as f:
    f.write(html)

print("✅ Layout updated - tabs removed, unified view created")
