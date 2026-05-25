/**
 * Navigation Handler
 * Handles showing/hiding sections when navigation links are clicked
 */

document.addEventListener('DOMContentLoaded', function() {
    // Ensure sections are hidden by default on page load
    hideAllSections();
    
    // Handle navigation link clicks
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link[href^="#"]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1); // Remove #
            
            if (targetId === 'top') {
                // Hide all sections and scroll to top
                hideAllSections();
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
                // Clear hash
                window.history.pushState('', document.title, window.location.pathname);
                return;
            }
            
            // Show and scroll to target section
            showSection(targetId);
        });
    });
    
    // Handle "Learn More" button
    const learnMoreBtn = document.querySelector('a[href="#about"]');
    if (learnMoreBtn) {
        learnMoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showSection('about');
        });
    }
    
    // Handle hash in URL (when page loads with #about or #contact)
    // Only show if hash is explicitly set
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        if (hash === 'about' || hash === 'contact') {
            showSection(hash);
        } else {
            // If hash is something else, hide all sections
            hideAllSections();
        }
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash.substring(1);
        if (hash === 'about' || hash === 'contact') {
            showSection(hash);
        } else if (hash === 'top' || hash === '' || !hash) {
            // Hide sections when going to top or no hash
            hideAllSections();
        }
    });
});

/**
 * Shows a specific section and scrolls to it
 */
function showSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    
    // Hide all sections first
    hideAllSections();
    
    // Show the target section
    section.style.display = 'block';
    
    // Update URL hash
    window.location.hash = sectionId;
    
    // Scroll to section with offset for fixed navbar
    const navbarHeight = 70; // Adjust based on your navbar height
    const sectionPosition = section.offsetTop - navbarHeight;
    
    setTimeout(() => {
        window.scrollTo({
            top: sectionPosition,
            behavior: 'smooth'
        });
    }, 100); // Small delay to ensure section is visible
}

/**
 * Hides all sections (About, Contact)
 */
function hideAllSections() {
    const aboutSection = document.getElementById('about');
    const contactSection = document.getElementById('contact');
    
    if (aboutSection) aboutSection.style.display = 'none';
    if (contactSection) contactSection.style.display = 'none';
}

