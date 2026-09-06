/**
 * Browser Fingerprint Generator
 * Generates a unique fingerprint based on browser characteristics
 */

(function() {
    'use strict';

    /**
     * Generate browser fingerprint
     * @returns {string} Base64 encoded fingerprint hash
     */
    function generateFingerprint() {
        const components = [];

        // User Agent
        components.push(navigator.userAgent || '');

        // Language
        components.push(navigator.language || navigator.userLanguage || '');

        // Languages array
        if (navigator.languages && navigator.languages.length) {
            components.push(navigator.languages.join(','));
        }

        // Screen properties
        if (screen) {
            components.push(screen.width + 'x' + screen.height);
            components.push(screen.colorDepth || 0);
            components.push(screen.pixelDepth || 0);
            if (screen.availWidth && screen.availHeight) {
                components.push(screen.availWidth + 'x' + screen.availHeight);
            }
        }

        // Timezone
        try {
            components.push(Intl.DateTimeFormat().resolvedOptions().timeZone || '');
            components.push(new Date().getTimezoneOffset().toString());
        } catch (e) {
            components.push('');
        }

        // Platform
        components.push(navigator.platform || '');

        // Hardware concurrency
        components.push(navigator.hardwareConcurrency || 0);

        // Device memory (if available)
        if (navigator.deviceMemory) {
            components.push(navigator.deviceMemory);
        }

        // Canvas fingerprint
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.textBaseline = 'top';
                ctx.font = '14px "Arial"';
                ctx.textBaseline = 'alphabetic';
                ctx.fillStyle = '#f60';
                ctx.fillRect(125, 1, 62, 20);
                ctx.fillStyle = '#069';
                ctx.fillText('Browser fingerprint test 🔒', 2, 15);
                ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
                ctx.fillText('Browser fingerprint test 🔒', 4, 17);
                components.push(canvas.toDataURL());
            }
        } catch (e) {
            components.push('');
        }

        // WebGL fingerprint
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    components.push(gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL));
                    components.push(gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL));
                }
                components.push(gl.getParameter(gl.VERSION));
                components.push(gl.getParameter(gl.SHADING_LANGUAGE_VERSION));
            }
        } catch (e) {
            components.push('');
        }

        // Audio context fingerprint
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                const context = new AudioContext();
                const oscillator = context.createOscillator();
                const analyser = context.createAnalyser();
                const gainNode = context.createGain();
                const scriptProcessor = context.createScriptProcessor(4096, 1, 1);

                gainNode.gain.value = 0;
                oscillator.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(gainNode);
                gainNode.connect(context.destination);
                oscillator.start(0);
                scriptProcessor.onaudioprocess = function(bins) {
                    const output = String(bins.inputBuffer.getChannelData(0).slice(0, 5));
                    components.push(output);
                    oscillator.stop();
                    context.close();
                };
            }
        } catch (e) {
            components.push('');
        }

        // Fonts detection (simplified)
        const baseFonts = ['monospace', 'sans-serif', 'serif'];
        const testFonts = ['Arial', 'Verdana', 'Times New Roman', 'Courier New', 'Georgia', 'Palatino', 'Garamond', 'Bookman', 'Comic Sans MS', 'Trebuchet MS', 'Impact'];
        const testString = 'mmmmmmmmmmlli';
        const testSize = '72px';
        const h = document.getElementsByTagName('body')[0];
        const s = document.createElement('span');
        s.style.fontSize = testSize;
        s.innerHTML = testString;
        const defaultWidth = {};
        const defaultHeight = {};

        for (let i = 0; i < baseFonts.length; i++) {
            s.style.fontFamily = baseFonts[i];
            h.appendChild(s);
            defaultWidth[baseFonts[i]] = s.offsetWidth;
            defaultHeight[baseFonts[i]] = s.offsetHeight;
            h.removeChild(s);
        }

        const detectedFonts = [];
        for (let i = 0; i < testFonts.length; i++) {
            let detected = false;
            for (let j = 0; j < baseFonts.length; j++) {
                s.style.fontFamily = testFonts[i] + ',' + baseFonts[j];
                h.appendChild(s);
                const matched = (s.offsetWidth !== defaultWidth[baseFonts[j]] || s.offsetHeight !== defaultHeight[baseFonts[j]]);
                h.removeChild(s);
                if (matched) {
                    detected = true;
                    break;
                }
            }
            if (detected) {
                detectedFonts.push(testFonts[i]);
            }
        }
        components.push(detectedFonts.join(','));

        // Plugin detection
        if (navigator.plugins && navigator.plugins.length) {
            const plugins = [];
            for (let i = 0; i < navigator.plugins.length; i++) {
                plugins.push(navigator.plugins[i].name);
            }
            components.push(plugins.join(','));
        }

        // Mime types
        if (navigator.mimeTypes && navigator.mimeTypes.length) {
            const mimeTypes = [];
            for (let i = 0; i < navigator.mimeTypes.length; i++) {
                mimeTypes.push(navigator.mimeTypes[i].type);
            }
            components.push(mimeTypes.join(','));
        }

        // Combine all components
        const fingerprintString = components.join('|');

        // Simple hash function (djb2)
        let hash = 5381;
        for (let i = 0; i < fingerprintString.length; i++) {
            hash = ((hash << 5) + hash) + fingerprintString.charCodeAt(i);
        }

        // Convert to base64-like string
        const hashString = hash.toString(36) + '_' + btoa(fingerprintString.substring(0, 100)).replace(/[^a-zA-Z0-9]/g, '').substring(0, 20);

        return hashString;
    }

    /**
     * Get or generate fingerprint
     * Stores in sessionStorage for consistency
     * @returns {string} Browser fingerprint
     */
    function getFingerprint() {
        const storageKey = 'bdc_fingerprint';
        
        // Try to get from sessionStorage first
        let fingerprint = sessionStorage.getItem(storageKey);
        
        if (!fingerprint) {
            // Generate new fingerprint
            fingerprint = generateFingerprint();
            
            // Store in sessionStorage
            try {
                sessionStorage.setItem(storageKey, fingerprint);
            } catch (e) {
                // Storage might be disabled, continue without storing
            }
        }
        
        return fingerprint;
    }

    // Expose to global scope
    window.bdcGetFingerprint = getFingerprint;
    
    // Also expose as jQuery plugin if jQuery is available
    if (typeof jQuery !== 'undefined') {
        jQuery.fn.bdcFingerprint = function() {
            return getFingerprint();
        };
    }

})();

