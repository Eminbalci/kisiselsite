document.addEventListener("DOMContentLoaded", () => {
    // 1. Animate skill bars when in view
    const skillBars = document.querySelectorAll(".skill-fill");
    
    const animateSkills = () => {
        skillBars.forEach(bar => {
            const rect = bar.getBoundingClientRect();
            const windowHeight = window.innerHeight || document.documentElement.clientHeight;
            
            // Check if skill bar is in viewport
            if (rect.top <= windowHeight - 50 && rect.bottom >= 0) {
                const percent = bar.getAttribute("data-percent");
                bar.style.width = `${percent}%`;
            }
        });
    };
    
    // 2. Change Nav style on Scroll using classes
    const navbar = document.getElementById("main-nav");
    const handleNavbarScroll = () => {
        if (!navbar) return;
        
        if (window.scrollY > 50) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
        }
    };

    // 3. Theme Toggle (Dark / Light Mode)
    const themeToggleBtn = document.getElementById("theme-toggle");
    if (themeToggleBtn) {
        const themeIcon = themeToggleBtn.querySelector(".theme-toggle-icon");
        
        // Update button icon based on current theme
        const updateIcon = (theme) => {
            if (themeIcon) {
                themeIcon.textContent = theme === 'light' ? '☀️' : '🌙';
            }
        };

        // Initialize icon
        const currentTheme = document.body.classList.contains("light-theme") ? "light" : "dark";
        updateIcon(currentTheme);

        themeToggleBtn.addEventListener("click", () => {
            const isLight = document.body.classList.toggle("light-theme");
            const newTheme = isLight ? "light" : "dark";
            localStorage.setItem("theme", newTheme);
            updateIcon(newTheme);
        });
    }

    // 4. Portfolio Category Filtering
    const filterTabs = document.querySelectorAll(".filter-tab");
    const portfolioCards = document.querySelectorAll(".portfolio-card");
    
    if (filterTabs.length > 0 && portfolioCards.length > 0) {
        filterTabs.forEach(tab => {
            tab.addEventListener("click", () => {
                // Remove active class from all tabs and add to clicked
                filterTabs.forEach(t => t.classList.remove("active"));
                tab.classList.add("active");
                
                const filterValue = tab.getAttribute("data-filter");
                
                portfolioCards.forEach(card => {
                    const cardCat = card.getAttribute("data-category");
                    
                    if (filterValue === "all") {
                        card.classList.remove("hide");
                    } else {
                        if (cardCat === filterValue) {
                            card.classList.remove("hide");
                        } else {
                            card.classList.add("hide");
                        }
                    }
                });
            });
        });
    }

    // Run animations once at start and bind to scroll
    animateSkills();
    handleNavbarScroll();

    window.addEventListener("scroll", () => {
        animateSkills();
        handleNavbarScroll();
    });

    // 5. Contact Form Submission
    const contactForm = document.getElementById("contact-form");
    if (contactForm) {
        contactForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById("contact-submit");
            const statusDiv = document.getElementById("contact-status");
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = "Gönderiliyor... / Sending...";
            statusDiv.style.display = "none";
            
            const formData = new FormData(contactForm);
            
            try {
                const response = await fetch("api/contact.php", {
                    method: "POST",
                    body: formData
                });
                
                const result = await response.json();
                
                statusDiv.style.display = "block";
                statusDiv.innerHTML = result.message;
                
                if (result.success) {
                    statusDiv.style.color = "#10b981"; // Success green
                    contactForm.reset();
                } else {
                    statusDiv.style.color = "#ef4444"; // Error red
                }
            } catch (error) {
                statusDiv.style.display = "block";
                statusDiv.style.color = "#ef4444";
                statusDiv.innerHTML = "Bir hata oluştu. Lütfen tekrar deneyin. / An error occurred. Please try again.";
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.getAttribute("data-original-text") || "Gönder";
            }
        });
        
        // Save original button text for restore
        const submitBtn = document.getElementById("contact-submit");
        if(submitBtn) {
            submitBtn.setAttribute("data-original-text", submitBtn.innerHTML.trim());
        }
    }
});
