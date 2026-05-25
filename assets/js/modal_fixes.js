/**
 * Modal Fixes - Replace common problematic patterns
 * 
 * This script patches common modal usage patterns to use ModalManager
 */

(function() {
  'use strict';
  
  // Wait for DOM and Bootstrap to be ready
  function init() {
    if (typeof bootstrap === 'undefined' || typeof ModalManager === 'undefined') {
      setTimeout(init, 100);
      return;
    }
    
    // Patch common modal patterns
    patchModalPatterns();
    
    // Fix inline onclick handlers
    fixInlineOnClickHandlers();
  }
  
  /**
   * Replace direct modal.show()/.hide() calls with ModalManager
   */
  function patchModalPatterns() {
    // This will be handled by ModalManager's delegation
    // But we can also patch common functions
    
    // Patch window functions that open modals
    const originalFunctions = {};
    
    // Common modal opening patterns to patch
    const modalOpeners = [
      'showAddUserModal',
      'openAddCurriculumModal',
      'openRoomScheduleModal',
      'openRoomAccessModal'
    ];
    
    modalOpeners.forEach(funcName => {
      if (window[funcName] && typeof window[funcName] === 'function') {
        originalFunctions[funcName] = window[funcName];
        // Don't override - let existing code work but ensure it uses proper lifecycle
      }
    });
  }
  
  /**
   * Fix inline onclick handlers that open modals
   */
  function fixInlineOnClickHandlers() {
    // Find all elements with onclick that might open modals
    const elementsWithOnclick = document.querySelectorAll('[onclick*="Modal"], [onclick*="modal"]');
    
    elementsWithOnclick.forEach(element => {
      const onclick = element.getAttribute('onclick');
      if (!onclick) return;
      
      // Extract modal ID from common patterns
      const modalIdMatch = onclick.match(/(?:getElementById|Modal)\(['"]([^'"]+)['"]\)/);
      if (modalIdMatch) {
        const modalId = modalIdMatch[1];
        
        // Remove inline onclick
        element.removeAttribute('onclick');
        
        // Add proper event listener
        element.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          ModalManager.open(modalId);
        });
      }
    });
  }
  
  // Initialize
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();










