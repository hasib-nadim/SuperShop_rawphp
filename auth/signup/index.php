<?php
require_once __DIR__ . '/../../_imports.php';
pageHead("Sign Up - Supershop", ["user_register.css"]);
?>
<div class="container">
    <div class="left-section">
        <div class="logo">🛒 Super Shop</div>
        <h1 class="welcome-text">Join Us Today!</h1>
        <p class="welcome-subtext">Create your account and unlock exclusive benefits, amazing deals, and personalized shopping experience.</p>

        <div class="features">
            <div class="feature-item">
                <div class="feature-icon">🎁</div>
                <div class="feature-text">
                    <h3>Exclusive Deals</h3>
                    <p>Get access to member-only discounts</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🚚</div>
                <div class="feature-text">
                    <h3>Free Shipping</h3>
                    <p>Free delivery on orders above $1000</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">⭐</div>
                <div class="feature-text">
                    <h3>Rewards Program</h3>
                    <p>Earn points with every purchase</p>
                </div>
            </div>
        </div>
    </div>

    <div class="right-section">
        <div class="form-header">
            <h2>Create Account</h2>
            <p>Fill in your details to get started</p>
        </div>

        <form action="" method="POST" id="registerForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name: </label>
                    <input type="text" id="firstName" name="firstName" placeholder="first name" required>
                    <div class="error-message" id="firstNameError"></div>
                </div>

                <div class="form-group">
                    <label for="lastName">Last Name: </label>
                    <input type="text" id="lastName" name="lastName" placeholder="last name (optional)" required>
                    <div class="error-message" id="lastNameError"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address: </label>
                <input type="email" id="email" name="email" placeholder="karim-momi-nadim-bisal@supershop.com" required>
                <div class="error-message" id="emailError"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number: </label>
                    <input type="tel" id="phone" name="phone" placeholder="+880 " required>
                    <div class="error-message" id="phoneError"></div>
                </div>

                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password: </label>
                <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
                <div class="password-hint">Use 8+ characters with mix of letters, numbers & symbols</div>
                <div class="error-message" id="passwordError"></div>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password: </label>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required>
                <div class="error-message" id="confirmPasswordError"></div>
            </div>

            <div class="terms-checkbox">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    I agree to the <a href="" target="_blank">Terms & Conditions</a> and <a href="" target="_blank">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">Create Account</button>
        </form>

        <div class="divider">
            <span>OR</span>
        </div>
<!-- 
        <div class="social-login">
            <button class="social-btn" type="button" onclick="registerWithGoogle()">
                <svg width="20" height="20" viewBox="0 0 20 20">
                    <path fill="#4285F4" d="M19.6 10.23c0-.82-.1-1.42-.25-2.05H10v3.72h5.5c-.15.96-.74 2.31-2.04 3.22v2.45h3.16c1.89-1.73 2.98-4.3 2.98-7.34z" />
                    <path fill="#34A853" d="M13.46 15.13c-.83.59-1.96 1-3.46 1-2.64 0-4.88-1.74-5.68-4.15H1.07v2.52C2.72 17.75 6.09 20 10 20c2.7 0 4.96-.89 6.62-2.42l-3.16-2.45z" />
                    <path fill="#FBBC05" d="M3.99 10c0-.69.12-1.35.32-1.97V5.51H1.07A9.973 9.973 0 000 10c0 1.61.39 3.14 1.07 4.49l3.24-2.52c-.2-.62-.32-1.28-.32-1.97z" />
                    <path fill="#EA4335" d="M10 3.88c1.88 0 3.13.81 3.85 1.48l2.84-2.76C14.96.99 12.7 0 10 0 6.09 0 2.72 2.25 1.07 5.51l3.24 2.52C5.12 5.62 7.36 3.88 10 3.88z" />
                </svg>
                Google
            </button>
            <button class="social-btn" type="button" onclick="registerWithFacebook()">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="#1877F2">
                    <path d="M20 10c0-5.523-4.477-10-10-10S0 4.477 0 10c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V10h2.54V7.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V10h2.773l-.443 2.89h-2.33v6.988C16.343 19.128 20 14.991 20 10z" />
                </svg>
                Facebook
            </button>
        </div> -->

        <div class="signin-link">
            Already have an account? <a href="/auth/login">Sign In</a>
        </div>
    </div>
</div>
<?php
pageFooter();
?>