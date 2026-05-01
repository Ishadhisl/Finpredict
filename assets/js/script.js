/**
 * FinPredict - JavaScript Functionality
 * Client-side form validation and interactivity
 */

// Form validation on document ready
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("creditPredictForm");

  if (form) {
    form.addEventListener("submit", function (e) {
      if (!validateForm()) {
        e.preventDefault();
        alert("Please correct the errors below before submitting.");
      }
    });

    // Real-time validation as user types
    setupRealtimeValidation();
  }

  // Setup calculation preview if on form page
  setupIncomePreview();
});

/**
 * Real-time validation feedback
 */
function setupRealtimeValidation() {
  const nameInput = document.getElementById("name");
  const emailInput = document.getElementById("email");
  const ageInput = document.getElementById("age");
  const incomeInput = document.getElementById("monthly_income");
  const creditLimitInput = document.getElementById("existing_credit_limit");

  if (nameInput) {
    nameInput.addEventListener("blur", function () {
      if (this.value.trim().length < 2) {
        showInputError(this, "Name must be at least 2 characters");
      } else {
        clearInputError(this);
      }
    });
  }

  if (emailInput) {
    emailInput.addEventListener("blur", function () {
      if (!validateEmail(this.value)) {
        showInputError(this, "Please enter a valid email address");
      } else {
        clearInputError(this);
      }
    });
  }

  if (ageInput) {
    ageInput.addEventListener("blur", function () {
      const age = parseInt(this.value);
      if (isNaN(age) || age < 18 || age > 100) {
        showInputError(this, "Age must be between 18 and 100");
      } else {
        clearInputError(this);
      }
    });
  }

  if (incomeInput) {
    incomeInput.addEventListener("blur", function () {
      const income = parseFloat(this.value);
      if (isNaN(income) || income < 0) {
        showInputError(this, "Income must be a positive number");
      } else {
        clearInputError(this);
      }
    });
  }

  if (creditLimitInput) {
    creditLimitInput.addEventListener("blur", function () {
      const limit = parseFloat(this.value);
      if (isNaN(limit) || limit < 0) {
        showInputError(this, "Credit limit must be a positive number");
      } else {
        clearInputError(this);
      }
    });
  }
}

/**
 * Display input error message
 */
function showInputError(inputElement, message) {
  // Remove existing error if any
  clearInputError(inputElement);

  // Add error class
  inputElement.classList.add("is-invalid");

  // Create and append error message
  const errorDiv = document.createElement("div");
  errorDiv.className = "invalid-feedback";
  errorDiv.textContent = message;
  inputElement.parentNode.insertBefore(errorDiv, inputElement.nextSibling);
}

/**
 * Clear input error message
 */
function clearInputError(inputElement) {
  inputElement.classList.remove("is-invalid");

  // Remove error message if it exists
  const nextElement = inputElement.nextElementSibling;
  if (nextElement && nextElement.classList.contains("invalid-feedback")) {
    nextElement.remove();
  }
}

/**
 * Validate entire form
 */
function validateForm() {
  let isValid = true;

  // Validate Name
  const name = document.getElementById("name");
  if (name && name.value.trim().length < 2) {
    showInputError(name, "Name must be at least 2 characters");
    isValid = false;
  }

  // Validate Email
  const email = document.getElementById("email");
  if (email && !validateEmail(email.value)) {
    showInputError(email, "Please enter a valid email address");
    isValid = false;
  }

  // Validate Age
  const age = document.getElementById("age");
  if (age) {
    const ageValue = parseInt(age.value);
    if (isNaN(ageValue) || ageValue < 18 || ageValue > 100) {
      showInputError(age, "Age must be between 18 and 100");
      isValid = false;
    }
  }

  // Validate Monthly Income
  const income = document.getElementById("monthly_income");
  if (income) {
    const incomeValue = parseFloat(income.value);
    if (isNaN(incomeValue) || incomeValue < 0) {
      showInputError(income, "Monthly income must be a positive number");
      isValid = false;
    }
  }

  // Validate Existing Credit Limit
  const creditLimit = document.getElementById("existing_credit_limit");
  if (creditLimit) {
    const limitValue = parseFloat(creditLimit.value);
    if (isNaN(limitValue) || limitValue < 0) {
      showInputError(
        creditLimit,
        "Existing credit limit must be a positive number",
      );
      isValid = false;
    }
  }

  // Validate Gender
  const gender = document.getElementById("gender");
  if (gender && gender.value === "") {
    showInputError(gender, "Please select a gender");
    isValid = false;
  }

  // Validate Education
  const education = document.getElementById("education_level");
  if (education && education.value === "") {
    showInputError(education, "Please select an education level");
    isValid = false;
  }

  // Validate Marital Status
  const marital = document.getElementById("marital_status");
  if (marital && marital.value === "") {
    showInputError(marital, "Please select a marital status");
    isValid = false;
  }

  return isValid;
}

/**
 * Validate email format
 */
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

/**
 * Setup income preview calculation
 */
function setupIncomePreview() {
  const incomeInput = document.getElementById("monthly_income");
  const previewElement = document.getElementById("incomePreview");

  if (incomeInput && previewElement) {
    incomeInput.addEventListener("input", function () {
      const income = parseFloat(this.value) || 0;
      const recommendation = getIncomeRecommendation(income);

      previewElement.innerHTML = `
                <div class="alert alert-info">
                    <strong>💡 Credit Limit Guidance:</strong> Based on your monthly income of LKR ${income.toLocaleString("en-US", { minimumFractionDigits: 2 })}, 
                    your maximum recommended credit limit would range from LKR ${(income * 1000).toLocaleString("en-US", { maximumFractionDigits: 0 })} to 
                    LKR ${(income * 3000).toLocaleString("en-US", { maximumFractionDigits: 0 })} depending on your risk assessment.
                </div>
            `;
    });
  }
}

/**
 * Get income recommendation guidance
 */
function getIncomeRecommendation(income) {
  if (income < 1000) {
    return "Your income suggests conservative credit limits. Build credit history gradually.";
  } else if (income < 3000) {
    return "You qualify for moderate credit limits. Maintain good payment history.";
  } else if (income < 5000) {
    return "You may qualify for substantial credit limits. Your financial health is important.";
  } else {
    return "You qualify for higher credit limits. Ensure responsible credit utilization.";
  }
}

/**
 * Format currency input
 */
function formatCurrency(value) {
  return "LKR " + parseFloat(value).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Reset form
 */
function resetForm() {
  const form = document.getElementById("creditPredictForm");
  if (form) {
    form.reset();

    // Clear all error messages
    const inputs = form.querySelectorAll("input, select");
    inputs.forEach((input) => {
      clearInputError(input);
    });
  }
}

/**
 * Print results page
 */
function printResults() {
  window.print();
}

/**
 * Add some animations on page load
 */
window.addEventListener("load", function () {
  // Fade in elements
  const elements = document.querySelectorAll(
    ".form-container, .results-container",
  );
  elements.forEach((el, index) => {
    el.style.opacity = "0";
    el.style.transform = "translateY(20px)";
    el.style.transition = `opacity 0.5s ease ${index * 0.1}s, transform 0.5s ease ${index * 0.1}s`;

    // Trigger animation
    setTimeout(() => {
      el.style.opacity = "1";
      el.style.transform = "translateY(0)";
    }, 50);
  });
});

// Validation error styling (add to CSS)
const style = document.createElement("style");
style.textContent = `
    .is-invalid {
        border-color: #e74c3c !important;
        background-color: #ffe6e6 !important;
    }
    
    .invalid-feedback {
        display: block;
        color: #e74c3c;
        font-size: 0.85rem;
        margin-top: 0.25rem;
        font-weight: 500;
    }
`;
document.head.appendChild(style);
