/**
 * Global Bootstrap Modal Manager
 * 
 * This module provides centralized modal management to prevent:
 * - Multiple modal instances
 * - aria-hidden state issues
 * - Backdrop conflicts
 * - Focus trapping problems
 * - Event listener duplication
 * 
 * Usage:
 *   ModalManager.open('modalId', options)
 *   ModalManager.close('modalId')
 *   ModalManager.getInstance('modalId')
 */

const ModalManager = (function() {
  'use strict';
  
  // Store all modal instances
  const modalInstances = new Map();
  
  // Store event handlers to prevent duplicates
  const eventHandlers = new Map();
  
  /**
   * Get or create a modal instance
   * @param {string} modalId - The ID of the modal element
   * @param {object} options - Bootstrap Modal options
   * @returns {bootstrap.Modal|null}
   */
  function getOrCreateInstance(modalId, options = {}) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
      console.error(`ModalManager: Modal element #${modalId} not found`);
      return null;
    }
    
    // Ensure modal is in body (Bootstrap requirement)
    if (modalElement.parentElement !== document.body) {
      document.body.appendChild(modalElement);
    }
    
    // Get existing instance or create new one
    let modal = bootstrap.Modal.getInstance(modalElement);
    if (!modal) {
      // Default options
      const defaultOptions = {
        backdrop: true,
        keyboard: true,
        focus: true
      };
      
      modal = new bootstrap.Modal(modalElement, { ...defaultOptions, ...options });
      modalInstances.set(modalId, modal);
    } else {
      modalInstances.set(modalId, modal);
    }
    
    return modal;
  }
  
  /**
   * Open a modal with proper state management
   * @param {string} modalId - The ID of the modal element
   * @param {object} options - Options including onShown callback
   * @returns {boolean} - Success status
   */
  function open(modalId, options = {}) {
    const modal = getOrCreateInstance(modalId, options);
    if (!modal) return false;
    
    const modalElement = document.getElementById(modalId);
    
    // If modal is already shown, hide it first to reset state
    if (modal._isShown || modalElement.classList.contains('show')) {
      close(modalId);
      // Wait for modal to be fully hidden before reopening
      const waitForHidden = () => {
        if (!modalElement.classList.contains('show') && 
            modalElement.hasAttribute('aria-hidden') && 
            modalElement.getAttribute('aria-hidden') === 'true') {
          // Modal is now hidden, proceed with opening
          proceedWithOpen(modalId, modal, options);
        } else {
          requestAnimationFrame(waitForHidden);
        }
      };
      requestAnimationFrame(waitForHidden);
      return true;
    }
    
    proceedWithOpen(modalId, modal, options);
    return true;
  }
  
  /**
   * Internal function to handle modal opening after state is ready
   */
  function proceedWithOpen(modalId, modal, options) {
    const modalElement = document.getElementById(modalId);
    
    // Remove existing event handlers to prevent duplicates
    const handlerKey = `${modalId}_shown`;
    const existingHandler = eventHandlers.get(handlerKey);
    if (existingHandler) {
      modalElement.removeEventListener('shown.bs.modal', existingHandler);
    }
    
    // Create new shown handler
    const onShown = function(event) {
      // Wait for aria-hidden to be properly removed
      const checkVisibility = () => {
        const isVisible = !modalElement.hasAttribute('aria-hidden') || 
                          modalElement.getAttribute('aria-hidden') === 'false';
        
        if (!isVisible) {
          requestAnimationFrame(checkVisibility);
          return;
        }
        
        // Modal is fully visible, call onShown callback if provided
        if (options.onShown && typeof options.onShown === 'function') {
          options.onShown(event);
        }
      };
      
      requestAnimationFrame(checkVisibility);
    };
    
    // Store handler
    eventHandlers.set(handlerKey, onShown);
    modalElement.addEventListener('shown.bs.modal', onShown, { once: true });
    
    // Show the modal
    modal.show();
  }
  
  /**
   * Close a modal with proper cleanup
   * @param {string} modalId - The ID of the modal element
   * @returns {boolean} - Success status
   */
  function close(modalId) {
    const modal = modalInstances.get(modalId);
    if (!modal) {
      const modalElement = document.getElementById(modalId);
      if (modalElement) {
        const instance = bootstrap.Modal.getInstance(modalElement);
        if (instance) {
          instance.hide();
          return true;
        }
      }
      return false;
    }
    
    modal.hide();
    return true;
  }
  
  /**
   * Get modal instance
   * @param {string} modalId - The ID of the modal element
   * @returns {bootstrap.Modal|null}
   */
  function getInstance(modalId) {
    return modalInstances.get(modalId) || getOrCreateInstance(modalId);
  }
  
  /**
   * Cleanup modal instance and handlers
   * @param {string} modalId - The ID of the modal element
   */
  function cleanup(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
      // Remove event handlers
      const handlerKey = `${modalId}_shown`;
      const handler = eventHandlers.get(handlerKey);
      if (handler) {
        modalElement.removeEventListener('shown.bs.modal', handler);
        eventHandlers.delete(handlerKey);
      }
    }
    
    // Dispose modal instance
    const modal = modalInstances.get(modalId);
    if (modal) {
      modal.dispose();
      modalInstances.delete(modalId);
    }
  }
  
  /**
   * Initialize modal event delegation for data-bs-toggle="modal"
   * This ensures all modals opened via data attributes use proper lifecycle
   */
  function initializeDelegation() {
    // Remove existing listener if any
    document.removeEventListener('click', handleModalClick);
    
    // Add event delegation
    document.addEventListener('click', handleModalClick);
  }
  
  /**
   * Handle clicks on elements with data-bs-toggle="modal"
   */
  function handleModalClick(event) {
    const target = event.target.closest('[data-bs-toggle="modal"]');
    if (!target) return;
    
    const modalId = target.getAttribute('data-bs-target');
    if (!modalId || !modalId.startsWith('#')) return;
    
    const actualModalId = modalId.substring(1);
    
    // Prevent default Bootstrap behavior and use our manager
    event.preventDefault();
    event.stopPropagation();
    
    // Open modal using our manager
    open(actualModalId);
  }
  
  /**
   * Fix aria-hidden issues by ensuring proper cleanup on modal close
   */
  function initializeCleanup() {
    document.addEventListener('hidden.bs.modal', function(event) {
      const modalElement = event.target;
      const modalId = modalElement.id;
      
      // Ensure aria-hidden is properly set
      if (!modalElement.hasAttribute('aria-hidden') || 
          modalElement.getAttribute('aria-hidden') !== 'true') {
        modalElement.setAttribute('aria-hidden', 'true');
      }
      
      // Remove show class if still present
      if (modalElement.classList.contains('show')) {
        modalElement.classList.remove('show');
      }
      
      // Cleanup backdrop if stuck - remove ALL backdrops
      const backdrops = document.querySelectorAll('.modal-backdrop');
      backdrops.forEach(backdrop => {
        backdrop.remove();
      });
      
      // Remove modal-open class from body
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      document.body.style.paddingRight = '';
      
      // Ensure no focus is trapped
      const focusedElement = document.activeElement;
      if (focusedElement && modalElement.contains(focusedElement)) {
        // Focus was trapped in modal, move it to body
        document.body.focus();
      }
    });
    
    // Also handle show.bs.modal to ensure proper state
    document.addEventListener('show.bs.modal', function(event) {
      const modalElement = event.target;
      
      // Cleanup any stuck backdrops before showing new modal
      const existingBackdrops = document.querySelectorAll('.modal-backdrop');
      existingBackdrops.forEach(backdrop => {
        backdrop.remove();
      });
      
      // Ensure no other modals are open
      const openModals = document.querySelectorAll('.modal.show');
      openModals.forEach(openModal => {
        if (openModal !== modalElement) {
          const openModalInstance = bootstrap.Modal.getInstance(openModal);
          if (openModalInstance) {
            openModalInstance.hide();
          }
        }
      });
    });
  }
  
  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initializeDelegation();
      initializeCleanup();
    });
  } else {
    initializeDelegation();
    initializeCleanup();
  }
  
  // Public API
  return {
    open: open,
    close: close,
    getInstance: getInstance,
    cleanup: cleanup,
    initializeDelegation: initializeDelegation
  };
})();

// Make available globally
window.ModalManager = ModalManager;










