const pageLoader = document.querySelector("[data-page-loader]");
const hidePageLoader = () => {
  if (!pageLoader || pageLoader.classList.contains("is-hidden")) return;
  pageLoader.classList.add("is-hidden");
  window.setTimeout(() => pageLoader.remove(), 500);
};

if (document.readyState === "complete") {
  hidePageLoader();
} else {
  window.addEventListener("load", hidePageLoader, { once: true });
}

document.querySelectorAll("[data-flash]").forEach((flash) => {
  window.setTimeout(() => {
    flash.style.transition = "opacity 250ms ease, transform 250ms ease";
    flash.style.opacity = "0";
    flash.style.transform = "translateY(-8px)";
    window.setTimeout(() => flash.remove(), 260);
  }, 3800);
});

document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
  const input = document.getElementById(toggle.dataset.passwordToggle);
  const icon = toggle.querySelector(".material-symbols-outlined");
  if (!input) return;
  toggle.addEventListener("click", () => {
    const visible = input.type === "text";
    input.type = visible ? "password" : "text";
    toggle.setAttribute(
      "aria-label",
      visible ? "Show password" : "Hide password",
    );
    if (icon) icon.textContent = visible ? "visibility" : "visibility_off";
  });
});

document.querySelectorAll("[data-quantity-input]").forEach((input) => {
  input.addEventListener("change", () => input.closest("form")?.submit());
});

document.querySelectorAll("[data-quantity-stepper]").forEach((stepper) => {
  const input = stepper.querySelector('input[name="quantity"]');
  if (!input) return;
  const updateQuantity = (change) => {
    const current = Number.parseInt(input.value, 10) || 1;
    input.value = String(Math.max(1, Math.min(10, current + change)));
  };
  stepper.querySelector("[data-quantity-decrease]")?.addEventListener("click", () => updateQuantity(-1));
  stepper.querySelector("[data-quantity-increase]")?.addEventListener("click", () => updateQuantity(1));
  input.addEventListener("change", () => {
    input.value = String(Math.max(1, Math.min(10, Number.parseInt(input.value, 10) || 1)));
  });
});

document.querySelectorAll("[data-description-collapsible]").forEach((description) => {
  const toggle = description.parentElement?.querySelector("[data-description-toggle]");
  if (!toggle) return;
  const updateToggle = () => {
    const isLong = description.scrollHeight > description.clientHeight + 2;
    toggle.classList.toggle("hidden", !isLong && !description.classList.contains("is-expanded"));
  };
  updateToggle();
  toggle.addEventListener("click", () => {
    const expanded = description.classList.toggle("is-expanded");
    description.classList.toggle("is-collapsed", !expanded);
    toggle.textContent = expanded ? "See less" : "See more";
    updateToggle();
  });
});

document.querySelectorAll("[data-rich-editor]").forEach((editor) => {
  const surface = editor.querySelector("[data-rich-editor-surface]");
  const input = editor.querySelector("[data-rich-editor-input]");
  if (!surface || !input) return;
  const sync = () => {
    input.value = surface.innerHTML;
  };
  surface.addEventListener("input", sync);
  surface.closest("form")?.addEventListener("submit", sync);
  editor.querySelectorAll("[data-rich-command]").forEach((button) => {
    button.addEventListener("mousedown", (event) => event.preventDefault());
    button.addEventListener("click", () => {
      surface.focus();
      document.execCommand(
        button.dataset.richCommand,
        false,
        button.dataset.richValue || null,
      );
      sync();
    });
  });
  sync();
});

document.querySelectorAll("[data-menu-toggle]").forEach((toggle) => {
  const menu = document.querySelector(
    toggle.dataset.menuTarget || "[data-menu]",
  );
  if (!menu) return;
  toggle.addEventListener("click", () => {
    const open = menu.classList.toggle("hidden") === false;
    toggle.setAttribute("aria-expanded", String(open));
    const icon = toggle.querySelector(".material-symbols-outlined");
    if (icon) icon.textContent = open ? "close" : "menu";
  });
});

const galleryMain = document.querySelector("#product-gallery-main");
const galleryThumbs = [...document.querySelectorAll("[data-gallery-image]")];
const galleryPrev = document.querySelector("[data-gallery-prev]");
const galleryNext = document.querySelector("[data-gallery-next]");
let galleryIndex = 0;
let galleryTimer;

const showGalleryImage = (nextIndex) => {
  if (!galleryMain || !galleryThumbs.length) return;
  galleryIndex = (nextIndex + galleryThumbs.length) % galleryThumbs.length;
  const thumbnail = galleryThumbs[galleryIndex];
  galleryMain.src = thumbnail.dataset.galleryImage || galleryMain.src;
  galleryMain.alt = thumbnail.dataset.galleryAlt || galleryMain.alt;
  galleryThumbs.forEach((item, index) => {
    const active = index === galleryIndex;
    item.classList.toggle("border-2", active);
    item.classList.toggle("border-black", active);
    item.classList.toggle("border-zinc-200", !active);
    item.querySelector("img")?.classList.toggle("opacity-70", !active);
  });
};

galleryThumbs.forEach((thumbnail, index) => {
  thumbnail.addEventListener("click", () => showGalleryImage(index));
});
galleryPrev?.addEventListener("click", () => showGalleryImage(galleryIndex - 1));
galleryNext?.addEventListener("click", () => showGalleryImage(galleryIndex + 1));
if (galleryThumbs.length > 1) {
  galleryTimer = window.setInterval(() => showGalleryImage(galleryIndex + 1), 5000);
}

const unavailablePaymentModal = document.querySelector(
  "[data-payment-unavailable-modal]",
);
const checkoutForm = document.querySelector("[data-checkout-form]");
const cashOnDelivery = document.querySelector(
  'input[name="payment_method"][value="cod"]',
);
const showUnavailablePayment = () => {
  if (!unavailablePaymentModal) return;
  unavailablePaymentModal.classList.remove("hidden");
  unavailablePaymentModal.classList.add("flex");
  unavailablePaymentModal.setAttribute("aria-hidden", "false");
};
const hideUnavailablePayment = () => {
  if (!unavailablePaymentModal) return;
  unavailablePaymentModal.classList.add("hidden");
  unavailablePaymentModal.classList.remove("flex");
  unavailablePaymentModal.setAttribute("aria-hidden", "true");
};
document.querySelectorAll("[data-unavailable-payment]").forEach((payment) => {
  payment.addEventListener("change", showUnavailablePayment);
});
checkoutForm?.addEventListener("submit", (event) => {
  if (checkoutForm.querySelector("[data-unavailable-payment]:checked")) {
    event.preventDefault();
    showUnavailablePayment();
  }
});
document.querySelectorAll("[data-payment-modal-close]").forEach((button) => {
  button.addEventListener("click", () => {
    if (cashOnDelivery) cashOnDelivery.checked = true;
    hideUnavailablePayment();
  });
});
unavailablePaymentModal?.addEventListener("click", (event) => {
  if (event.target === unavailablePaymentModal) hideUnavailablePayment();
});
