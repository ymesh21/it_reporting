<?php
// protection.php
class ContentProtection {
    
    public static function getProtectionScript() {
        return '
        <script>
        // Comprehensive protection script
        (function() {
            "use strict";
            
            // Disable right-click
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
                showProtectionMessage("Right-click is disabled");
                return false;
            });
            
            // Disable text selection
            document.addEventListener("selectstart", function(e) {
                e.preventDefault();
                return false;
            });
            
            // Disable copy
            document.addEventListener("copy", function(e) {
                e.preventDefault();
                showProtectionMessage("Copying content is not allowed");
                return false;
            });
            
            // Disable cut
            document.addEventListener("cut", function(e) {
                e.preventDefault();
                return false;
            });
            
            // Disable drag
            document.addEventListener("dragstart", function(e) {
                e.preventDefault();
                return false;
            });
            
            // Keyboard shortcuts protection
            document.addEventListener("keydown", function(e) {
                // Disable F12
                if (e.key === "F12") {
                    e.preventDefault();
                    showProtectionMessage("Developer tools are disabled");
                    return false;
                }
                
                // Disable Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
                if (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J" || e.key === "C")) {
                    e.preventDefault();
                    showProtectionMessage("Developer tools are disabled");
                    return false;
                }
                
                // Disable Ctrl+U (View source)
                if (e.ctrlKey && e.key === "u") {
                    e.preventDefault();
                    showProtectionMessage("Viewing source is disabled");
                    return false;
                }
                
                // Disable Ctrl+S (Save page)
                if (e.ctrlKey && e.key === "s") {
                    e.preventDefault();
                    showProtectionMessage("Saving page is disabled");
                    return false;
                }
            });
            
            // Developer tools detection
            let devToolsOpen = false;
            const threshold = 160;
            
            setInterval(function() {
                const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                const heightThreshold = window.outerHeight - window.innerHeight > threshold;
                
                if (!devToolsOpen && (widthThreshold || heightThreshold)) {
                    devToolsOpen = true;
                    document.body.innerHTML = \'<div style="padding: 50px; text-align: center; font-family: Arial, sans-serif;"><h1 style="color: #dc3545;">Access Restricted</h1><p>Developer tools detection active. This action has been logged.</p><p>Please close developer tools to continue.</p></div>\';
                }
            }, 1000);
            
            function showProtectionMessage(message) {
                // Create toast message
                const toast = document.createElement("div");
                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #dc3545;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 5px;
                    z-index: 10000;
                    font-family: Arial, sans-serif;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                `;
                toast.textContent = message;
                document.body.appendChild(toast);
                
                // Remove after 3 seconds
                setTimeout(function() {
                    document.body.removeChild(toast);
                }, 3000);
            }
            
            // Add protection overlay for sensitive areas
            const sensitiveElements = document.querySelectorAll(".sensitive-content, [data-protected]");
            sensitiveElements.forEach(function(element) {
                element.style.cssText += "; position: relative;";
                
                const overlay = document.createElement("div");
                overlay.style.cssText = `
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: transparent;
                    z-index: 10;
                    cursor: default;
                `;
                element.appendChild(overlay);
            });
            
        })();
        </script>
        ';
    }
    
    public static function getProtectionCSS() {
        return '
        <style>
        .sensitive-content, [data-protected] {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        img, .no-drag {
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }
        
        @media print {
            body * {
                visibility: hidden !important;
            }
            .no-print, .no-print * {
                display: none !important;
            }
        }
        </style>
        ';
    }
}
?>