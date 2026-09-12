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

document.querySelectorAll("[data-expand-toggle]").forEach((toggle) => {
  const target = document.querySelector(toggle.dataset.expandTarget || "");
  if (!target) return;
  const moreLabel = toggle.dataset.expandMore || "See more";
  const lessLabel = toggle.dataset.expandLess || "See less";
  toggle.addEventListener("click", () => {
    const expanded = target.classList.toggle("is-expanded");
    toggle.setAttribute("aria-expanded", String(expanded));
    toggle.textContent = expanded ? lessLabel : moreLabel;
  });
});

document.querySelectorAll("[data-live-search]").forEach((form) => {
  const input = form.querySelector('input[name="q"]');
  const resultsBox = form.querySelector("[data-live-search-results]");
  const endpoint = form.dataset.searchEndpoint;
  if (!input || !resultsBox || !endpoint) return;

  let timer;
  let controller;
  const hideResults = () => {
    resultsBox.hidden = true;
    resultsBox.replaceChildren();
  };
  const showResults = (results) => {
    resultsBox.replaceChildren();
    if (!results.length) {
      const empty = document.createElement("p");
      empty.className = "px-4 py-3 text-xs text-zinc-500";
      empty.textContent = "No matching frames found.";
      resultsBox.append(empty);
      resultsBox.hidden = false;
      return;
    }
    results.forEach((result) => {
      const link = document.createElement("a");
      link.className = "block border-b border-zinc-100 px-4 py-3 last:border-0 hover:bg-zinc-50";
      link.href = `${form.dataset.searchBase || ""}product?slug=${encodeURIComponent(result.slug)}`;
      const name = document.createElement("strong");
      name.className = "block truncate text-xs text-ink";
      name.textContent = result.name;
      const meta = document.createElement("span");
      meta.className = "mt-1 block truncate text-[11px] text-zinc-500";
      meta.textContent = [result.brand, result.price].filter(Boolean).join(" · ");
      link.append(name, meta);
      resultsBox.append(link);
    });
    resultsBox.hidden = false;
  };

  input.addEventListener("input", () => {
    window.clearTimeout(timer);
    controller?.abort();
    const query = input.value.trim();
    if (query.length < 2) {
      hideResults();
      return;
    }
    timer = window.setTimeout(async () => {
      controller = new AbortController();
      try {
        const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
          headers: { Accept: "application/json" },
          signal: controller.signal,
        });
        if (!response.ok) throw new Error("Search request failed");
        showResults((await response.json()).results || []);
      } catch (error) {
        if (error.name !== "AbortError") hideResults();
      }
    }, 180);
  });
  input.addEventListener("focus", () => {
    if (input.value.trim().length >= 2) input.dispatchEvent(new Event("input"));
  });
  document.addEventListener("click", (event) => {
    if (!form.contains(event.target)) hideResults();
  });
});

document.querySelectorAll("[data-address-select]").forEach((select) => {
  const form = select.closest("form");
  const setValue = (name, value) => {
    const field = form?.querySelector(`[name="${name}"]`);
    if (field) field.value = value || "";
  };
  const fillAddress = () => {
    const option = select.selectedOptions[0];
    if (!option || option.value === "0") return;
    const nameParts = (option.dataset.recipient || "").trim().split(/\s+/, 2);
    setValue("first_name", nameParts[0] || "");
    setValue("last_name", nameParts[1] || "");
    setValue("phone", option.dataset.phone);
    setValue("address", [option.dataset.line1, option.dataset.line2].filter(Boolean).join(", "));
    setValue("city", option.dataset.city);
    setValue("state", option.dataset.state);
    setValue("district", option.dataset.district);
    setValue("municipality", option.dataset.municipality);
    setValue("ward_number", option.dataset.ward);
    setValue("tole_locality", option.dataset.tole);
    setValue("street_chowk", option.dataset.street);
    setValue("house_number", option.dataset.house);
    setValue("nearby_landmark", option.dataset.landmark);
    setValue("postal_code", option.dataset.postal);
  };
  select.addEventListener("change", fillAddress);
  if (select.value !== "0") fillAddress();
});
