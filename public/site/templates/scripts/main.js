const initHeroTabs = () => {
  const tabs = document.querySelector(".hero-tabs-group");
  if (!tabs) return;

  const indicator = tabs.querySelector(".tab-indicator");
  const hover = tabs.querySelector(".tab-hover");
  const buttons = Array.from(tabs.querySelectorAll(".hero-tab"));
  if (!indicator || !hover || buttons.length === 0) return;

  const setIndicator = (el, target) => {
    const rect = el.getBoundingClientRect();
    const parent = tabs.getBoundingClientRect();
    const offset = rect.left - parent.left;
    target.style.width = `${rect.width}px`;
    target.style.transform = `translateX(${offset}px)`;
  };

  const setActive = (btn) => {
    buttons.forEach((b) => b.classList.toggle("is-active", b === btn));
    setIndicator(btn, indicator);
  };

  const active = tabs.querySelector(".hero-tab.is-active") || buttons[0];
  setActive(active);

  buttons.forEach((btn) => {
    btn.addEventListener("click", () => setActive(btn));
    btn.addEventListener("mouseenter", () => {
      setIndicator(btn, hover);
      hover.style.opacity = "1";
    });
    btn.addEventListener("focus", () => {
      setIndicator(btn, hover);
      hover.style.opacity = "1";
    });
  });

  tabs.addEventListener("mouseleave", () => {
    hover.style.opacity = "0";
  });

  window.addEventListener("resize", () => {
    const current = tabs.querySelector(".hero-tab.is-active") || buttons[0];
    setIndicator(current, indicator);
  });
};

const initTourNavTabs = () => {
  const tabs = document.querySelector(".tour-nav-group");
  if (!tabs) return;

  const indicator = tabs.querySelector(".tour-nav-indicator");
  const hover = tabs.querySelector(".tour-nav-hover");
  const links = Array.from(tabs.querySelectorAll(".tour-nav-link"));
  if (!indicator || !hover || links.length === 0) return;

  const setIndicator = (el, target) => {
    const rect = el.getBoundingClientRect();
    const parent = tabs.getBoundingClientRect();
    const offset = rect.left - parent.left;
    target.style.width = `${rect.width}px`;
    target.style.transform = `translateX(${offset}px)`;
  };

  const setActive = (el) => {
    links.forEach((link) => link.classList.toggle("is-active", link === el));
    setIndicator(el, indicator);
  };

  const active = tabs.querySelector(".tour-nav-link.is-active") || links[0];
  setActive(active);

  links.forEach((link) => {
    link.addEventListener("mouseenter", () => {
      setIndicator(link, hover);
      hover.style.opacity = "1";
    });
    link.addEventListener("focus", () => {
      setIndicator(link, hover);
      hover.style.opacity = "1";
    });
  });

  tabs.addEventListener("mouseleave", () => {
    hover.style.opacity = "0";
  });

  window.addEventListener("resize", () => {
    const current = tabs.querySelector(".tour-nav-link.is-active") || links[0];
    setIndicator(current, indicator);
  });
};

const initPeoplePicker = () => {
  const fields = Array.from(document.querySelectorAll(".hero-field-people"));
  if (fields.length === 0) return;

  const formatLabel = (value, singular, few, many) => {
    const mod10 = value % 10;
    const mod100 = value % 100;
    if (mod10 === 1 && mod100 !== 11) return `${value} ${singular}`;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return `${value} ${few}`;
    return `${value} ${many}`;
  };

  const pickers = [];

  fields.forEach((field) => {
    const input = field.querySelector('input:not([type="hidden"])');
    const hiddenInput = field.querySelector('input[type="hidden"]');
    const popover = field.querySelector(".people-popover");
    const countEl = field.querySelector(".people-count");
    const minusBtn = field.querySelector("[data-action='minus']");
    const plusBtn = field.querySelector("[data-action='plus']");
    if (!input || !popover || !countEl || !minusBtn || !plusBtn) return;

    const min = Math.max(1, Number(field.dataset.peopleMin) || 1);
    const rawMax = Number(field.dataset.peopleMax);
    const max = Number.isFinite(rawMax) && rawMax >= min ? rawMax : 50;
    const singular = field.dataset.peopleUnitSingular || "человек";
    const few = field.dataset.peopleUnitFew || "человека";
    const many = field.dataset.peopleUnitMany || "человек";

    const valueFromInput = Number(String(input.value || "").replace(/\D+/g, ""));
    const valueFromHidden = hiddenInput ? Number(hiddenInput.value) : NaN;
    let count = Number.isFinite(valueFromHidden) && valueFromHidden > 0 ? valueFromHidden : valueFromInput;
    if (!Number.isFinite(count) || count < min) count = min;
    if (count > max) count = max;

    const setCount = (value) => {
      count = Math.max(min, Math.min(max, value));
      countEl.textContent = String(count);
      input.value = formatLabel(count, singular, few, many);
      if (hiddenInput) hiddenInput.value = String(count);
    };

    const open = () => {
      popover.classList.add("is-open");
      popover.setAttribute("aria-hidden", "false");
    };

    const close = () => {
      popover.classList.remove("is-open");
      popover.setAttribute("aria-hidden", "true");
    };

    pickers.push({ field, open, close, isOpen: () => popover.classList.contains("is-open") });
    setCount(count);

    input.addEventListener("click", (event) => {
      event.stopPropagation();
      pickers.forEach((picker) => {
        if (picker.field !== field) picker.close();
      });
      open();
    });

    field.addEventListener("click", (event) => {
      if (event.target instanceof Element && event.target.closest(".people-popover")) return;
      event.stopPropagation();
      pickers.forEach((picker) => {
        if (picker.field !== field) picker.close();
      });
      open();
    });

    minusBtn.addEventListener("click", (event) => {
      event.stopPropagation();
      setCount(count - 1);
    });

    plusBtn.addEventListener("click", (event) => {
      event.stopPropagation();
      setCount(count + 1);
    });
  });

  document.addEventListener("click", (event) => {
    pickers.forEach((picker) => {
      if (!(event.target instanceof Node) || !picker.field.contains(event.target)) {
        picker.close();
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    pickers.forEach((picker) => {
      if (picker.isOpen()) picker.close();
    });
  });
};

const initHotelDateFields = () => {
  const dateInputs = Array.from(document.querySelectorAll("input[data-hotel-date]"));
  if (dateInputs.length === 0) return;

  dateInputs.forEach((input) => {
    const setTextMode = () => {
      if (input.value.trim()) return;
      input.type = "text";
    };

    if (!input.value.trim()) {
      input.type = "text";
    }

    input.addEventListener("focus", () => {
      if (input.type !== "date") input.type = "date";
    });

    input.addEventListener("click", () => {
      if (input.type !== "date") input.type = "date";
      if (typeof input.showPicker === "function") {
        try {
          input.showPicker();
        } catch (error) {
          // Ignore unsupported browsers.
        }
      }
    });

    input.addEventListener("blur", setTextMode);
  });
};

const initWhereFieldAutoGrow = () => {
  const field = document.querySelector(".hero-field-where");
  if (!field) return;

  const input = field.querySelector("input[name='where']");
  const fields = document.querySelector(".hero-search-fields");
  if (!input || !fields) return;

  const inputStyles = window.getComputedStyle(input);
  const fieldStyles = window.getComputedStyle(field);
  const baseInputWidth = Math.ceil(parseFloat(inputStyles.width)) || 150;
  const baseFieldMinWidth = Math.ceil(parseFloat(fieldStyles.minWidth)) || Math.ceil(field.getBoundingClientRect().width);

  const measure = document.createElement("span");
  measure.style.position = "absolute";
  measure.style.visibility = "hidden";
  measure.style.whiteSpace = "pre";
  measure.style.left = "-9999px";
  measure.style.top = "0";
  measure.style.font = inputStyles.font;
  measure.style.fontFamily = inputStyles.fontFamily;
  measure.style.fontSize = inputStyles.fontSize;
  measure.style.fontWeight = inputStyles.fontWeight;
  measure.style.letterSpacing = inputStyles.letterSpacing;
  document.body.appendChild(measure);

  const paddingLeft = parseFloat(fieldStyles.paddingLeft) || 0;
  const paddingRight = parseFloat(fieldStyles.paddingRight) || 0;
  const gap = parseFloat(fieldStyles.columnGap || fieldStyles.gap) || 12;
  const textBuffer = 18;
  const icon = field.querySelector("img");
  const iconWidth = icon ? Math.ceil(icon.getBoundingClientRect().width) : 18;

  const reset = () => {
    input.style.width = "";
    field.style.minWidth = "";
  };

  const update = () => {
    const isStacked = window.getComputedStyle(fields).flexDirection === "column";
    if (isStacked) {
      reset();
      return;
    }

    const value = input.value.trim();
    if (!value) {
      reset();
      return;
    }

    measure.textContent = value;
    const textWidth = Math.ceil(measure.getBoundingClientRect().width);
    const targetInputWidth = Math.max(baseInputWidth, textWidth + textBuffer);
    input.style.width = `${targetInputWidth}px`;

    const targetFieldMinWidth = Math.max(
      baseFieldMinWidth,
      Math.ceil(targetInputWidth + iconWidth + gap + paddingLeft + paddingRight)
    );
    field.style.minWidth = `${targetFieldMinWidth}px`;
  };

  input.addEventListener("input", update);
  input.addEventListener("change", update);
  window.addEventListener("resize", update);
  update();
};

const lockModalScroll = () => {
  document.body.classList.add("auth-modal-open");
};

const unlockModalScroll = () => {
  const hasOpenModal = document.querySelector(".auth-modal.is-open");
  if (!hasOpenModal) {
    document.body.classList.remove("auth-modal-open");
  }
};

const initAuthModal = () => {
  const modal = document.querySelector("#auth-modal");
  if (!modal) return;

  const backdrop = modal.querySelector(".auth-modal-backdrop");
  const closeButtons = Array.from(modal.querySelectorAll("[data-auth-close]"));
  const messageEl = modal.querySelector("[data-auth-message]");
  const apiUrl = modal.dataset.authApiUrl || window.location.pathname;
  const csrfName = modal.dataset.csrfName || "";
  const csrfValue = modal.dataset.csrfValue || "";
  const forms = Array.from(modal.querySelectorAll("[data-auth-form]"));
  const paneByMode = {
    login: modal.querySelector('[data-auth-pane="login"]'),
    register: modal.querySelector('[data-auth-pane="register"]'),
  };
  let activeMode = "login";

  const setMessage = (text, type = "info") => {
    if (!messageEl) return;
    messageEl.textContent = text || "";
    messageEl.classList.remove("is-error", "is-success");
    if (type === "error") messageEl.classList.add("is-error");
    if (type === "success") messageEl.classList.add("is-success");
  };

  const setPane = (mode) => {
    activeMode = mode === "register" ? "register" : "login";
    Object.entries(paneByMode).forEach(([paneMode, pane]) => {
      if (!pane) return;
      pane.classList.toggle("is-active", paneMode === activeMode);
    });
    setMessage("");
  };

  const openModal = (mode = "login") => {
    setPane(mode);
    modal.hidden = false;
    window.requestAnimationFrame(() => {
      modal.classList.add("is-open");
      lockModalScroll();
    });

    const targetPane = paneByMode[activeMode];
    const firstInput = targetPane ? targetPane.querySelector("input") : null;
    if (firstInput) firstInput.focus();
  };

  const closeModal = () => {
    modal.classList.remove("is-open");
    window.setTimeout(() => {
      if (!modal.classList.contains("is-open")) {
        modal.hidden = true;
      }
      unlockModalScroll();
    }, 160);
  };

  const sendRequest = async (action, payload) => {
    const params = new URLSearchParams();
    params.set("auth_action", action);
    Object.entries(payload).forEach(([key, value]) => {
      params.set(key, String(value ?? ""));
    });
    if (csrfName && csrfValue) {
      params.set(csrfName, csrfValue);
    }

    const response = await fetch(apiUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      body: params.toString(),
      credentials: "same-origin",
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      data = null;
    }

    if (!data || typeof data !== "object") {
      throw new Error("Сервер вернул некорректный ответ.");
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.message || "Не удалось выполнить запрос.");
    }

    return data;
  };

  const setBusy = (form, busy) => {
    form.classList.toggle("is-busy", busy);
    const submitBtn = form.querySelector(".auth-submit-btn");
    if (submitBtn instanceof HTMLButtonElement) {
      submitBtn.disabled = busy;
    }

    const codeInput = form.querySelector('input[name="code"]');
    if (codeInput instanceof HTMLInputElement) {
      codeInput.readOnly = busy;
      if (busy) {
        codeInput.setAttribute("aria-busy", "true");
      } else {
        codeInput.removeAttribute("aria-busy");
      }
    }
  };

  const setupCooldown = (button, seconds) => {
    const total = Number(seconds) || 60;
    let left = total;
    button.disabled = true;
    button.dataset.defaultLabel = button.dataset.defaultLabel || button.textContent || "получить код";
    button.textContent = `${left} сек`;

    const timer = window.setInterval(() => {
      left -= 1;
      if (left <= 0) {
        window.clearInterval(timer);
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || "получить код";
        return;
      }
      button.textContent = `${left} сек`;
    }, 1000);
  };

  forms.forEach((form) => {
    const mode = form.dataset.authForm === "register" ? "register" : "login";
    const emailInput = form.querySelector('input[name="email"]');
    const nameInput = form.querySelector('input[name="name"]');
    const codeInput = form.querySelector('input[name="code"]');
    const sendCodeBtn = form.querySelector("[data-auth-send-code]");
    const submitBtn = form.querySelector(".auth-submit-btn");
    if (!emailInput || !codeInput || !sendCodeBtn || !submitBtn) return;

    codeInput.addEventListener("input", () => {
      codeInput.value = codeInput.value.replace(/\D+/g, "").slice(0, 6);
    });

    sendCodeBtn.addEventListener("click", async () => {
      const payload = {
        mode,
        email: emailInput.value.trim(),
      };
      if (mode === "register" && nameInput) {
        payload.name = nameInput.value.trim();
      }

      sendCodeBtn.disabled = true;
      try {
        const data = await sendRequest("send_code", payload);
        const debugCode = data.data && data.data.debug_code ? String(data.data.debug_code) : "";
        const successMessage = debugCode
          ? `${data.message || "Код отправлен на почту."} Тестовый код: ${debugCode}`
          : data.message || "Код отправлен на почту.";
        setMessage(successMessage, "success");
        setupCooldown(sendCodeBtn, (data.data && data.data.cooldown) || 60);
        codeInput.focus();
      } catch (error) {
        setMessage(error instanceof Error ? error.message : "Не удалось отправить код.", "error");
        sendCodeBtn.disabled = false;
      }
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      const payload = {
        mode,
        email: emailInput.value.trim(),
        code: codeInput.value.trim(),
      };
      if (mode === "register" && nameInput) {
        payload.name = nameInput.value.trim();
      }

      setBusy(form, true);
      try {
        const data = await sendRequest("verify_code", payload);
        setMessage(data.message || "Успешный вход.", "success");
        const redirect = (data.data && data.data.redirect) || "/profile/";
        window.setTimeout(() => {
          window.location.href = redirect;
        }, 250);
      } catch (error) {
        setMessage(error instanceof Error ? error.message : "Не удалось подтвердить код.", "error");
      } finally {
        setBusy(form, false);
      }
    });
  });

  Array.from(document.querySelectorAll("[data-auth-open]")).forEach((trigger) => {
    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      const mode = trigger.getAttribute("data-auth-mode") || "login";
      openModal(mode);
    });
  });

  Array.from(modal.querySelectorAll("[data-auth-switch]")).forEach((btn) => {
    btn.addEventListener("click", () => {
      const mode = btn.getAttribute("data-auth-switch") || "login";
      setPane(mode);
    });
  });

  if (backdrop) {
    backdrop.addEventListener("click", closeModal);
  }
  closeButtons.forEach((btn) => btn.addEventListener("click", closeModal));

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal.classList.contains("is-open")) {
      closeModal();
    }
  });
};

const initContactsModal = () => {
  const modal = document.querySelector("#contacts-modal");
  if (!modal) return;

  const backdrop = modal.querySelector(".auth-modal-backdrop");
  const closeButtons = Array.from(modal.querySelectorAll("[data-contacts-close]"));
  const openButtons = Array.from(document.querySelectorAll("[data-contacts-open]"));
  if (openButtons.length === 0) return;

  const openModal = () => {
    modal.hidden = false;
    window.requestAnimationFrame(() => {
      modal.classList.add("is-open");
      lockModalScroll();
    });
  };

  const closeModal = () => {
    modal.classList.remove("is-open");
    window.setTimeout(() => {
      if (!modal.classList.contains("is-open")) {
        modal.hidden = true;
      }
      unlockModalScroll();
    }, 160);
  };

  openButtons.forEach((trigger) => {
    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      openModal();
    });
  });

  if (backdrop) {
    backdrop.addEventListener("click", closeModal);
  }
  closeButtons.forEach((button) => button.addEventListener("click", closeModal));

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal.classList.contains("is-open")) {
      closeModal();
    }
  });
};

const initAuthLogout = () => {
  const button = document.querySelector("[data-auth-logout]");
  if (!button) return;

  button.addEventListener("click", async () => {
    const apiUrl = button.getAttribute("data-auth-api-url") || window.location.pathname;
    const csrfName = button.getAttribute("data-csrf-name") || "";
    const csrfValue = button.getAttribute("data-csrf-value") || "";

    const params = new URLSearchParams();
    params.set("auth_action", "logout");
    if (csrfName && csrfValue) {
      params.set(csrfName, csrfValue);
    }

    button.disabled = true;
    try {
      const response = await fetch(apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        body: params.toString(),
        credentials: "same-origin",
      });

      const data = await response.json();
      if (!response.ok || !data || !data.ok) {
        throw new Error((data && data.message) || "Не удалось выйти из аккаунта.");
      }

      const redirect = (data.data && data.data.redirect) || "/";
      window.location.href = redirect;
    } catch (error) {
      const message = error instanceof Error ? error.message : "Не удалось выйти из аккаунта.";
      window.alert(message);
      button.disabled = false;
    }
  });
};

const initJournalSlider = () => {
  const slider = document.querySelector(".journal-card");
  if (!slider) return;

  const articles = Array.from(slider.querySelectorAll(".journal-article"));
  if (articles.length < 2) return;

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (prefersReducedMotion) return;

  let index = articles.findIndex((item) => item.classList.contains("is-active"));
  if (index === -1) index = 0;
  articles.forEach((item, i) => item.classList.toggle("is-active", i === index));

  const advance = () => {
    const current = articles[index];
    const nextIndex = (index + 1) % articles.length;
    const next = articles[nextIndex];

    current.classList.add("is-exit");
    next.classList.add("is-active", "is-enter");

    window.setTimeout(() => {
      current.classList.remove("is-active", "is-exit");
      next.classList.remove("is-enter");
    }, 520);

    index = nextIndex;
  };

  let timer = window.setInterval(advance, 10000);

  slider.addEventListener("mouseenter", () => {
    window.clearInterval(timer);
  });

  slider.addEventListener("mouseleave", () => {
    timer = window.setInterval(advance, 10000);
  });
};

const initHotToursSlider = () => {
  const section = document.querySelector(".section--hot-tours");
  if (!section) return;

  const grid = section.querySelector(".hot-tours-grid");
  const track = section.querySelector(".hot-tours-track");
  const prevBtn = section.querySelector(".hot-tours-prev");
  const nextBtn = section.querySelector(".hot-tours-next");
  if (!grid || !track || !prevBtn || !nextBtn) return;

  const cards = Array.from(track.querySelectorAll(".hot-tour-card"));
  if (cards.length === 0) return;

  const getVisibleCount = () => (window.innerWidth <= 720 ? 2 : 5);
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  let startIndex = 0;

  const update = () => {
    const visibleCount = Math.max(1, getVisibleCount());
    const maxStart = Math.max(0, cards.length - visibleCount);
    startIndex = Math.min(startIndex, maxStart);
    const cardWidth = cards[0].getBoundingClientRect().width;
    const styles = window.getComputedStyle(track);
    const gap = parseFloat(styles.columnGap || styles.gap || "0");
    const offset = (cardWidth + gap) * startIndex;
    track.style.transform = `translateX(-${offset}px)`;
    track.style.transition = prefersReducedMotion ? "none" : "transform 420ms ease";

    const shouldSlide = cards.length > visibleCount;
    prevBtn.disabled = !shouldSlide || startIndex <= 0;
    nextBtn.disabled = !shouldSlide || startIndex >= maxStart;
    prevBtn.classList.toggle("is-disabled", prevBtn.disabled);
    nextBtn.classList.toggle("is-disabled", nextBtn.disabled);
  };

  prevBtn.addEventListener("click", () => {
    startIndex = Math.max(0, startIndex - 1);
    update();
  });

  nextBtn.addEventListener("click", () => {
    const visibleCount = Math.max(1, getVisibleCount());
    const maxStart = Math.max(0, cards.length - visibleCount);
    startIndex = Math.min(maxStart, startIndex + 1);
    update();
  });

  window.addEventListener("resize", update);
  update();
};

const initDagestanSlider = () => {
  const section = document.querySelector(".section--places");
  if (!section) return;

  const banner = section.querySelector(".places-banner");
  const grid = section.querySelector(".places-grid");
  const track = section.querySelector(".places-track");
  const prevBtn = section.querySelector(".places-prev");
  const nextBtn = section.querySelector(".places-next");
  if (!banner || !grid || !track || !prevBtn || !nextBtn) return;

  const cards = Array.from(track.querySelectorAll(".place-card"));
  if (cards.length === 0) return;

  const getVisibleCount = () => (window.innerWidth <= 720 ? 2 : 5);
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  let startIndex = 0;

  const update = () => {
    const hasSlider = cards.length > 5;
    banner.classList.toggle("places-banner--slider", hasSlider);

    if (!hasSlider) {
      startIndex = 0;
      track.style.transform = "";
      track.style.transition = "";
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      prevBtn.classList.add("is-disabled");
      nextBtn.classList.add("is-disabled");
      return;
    }

    const visibleCount = Math.max(1, getVisibleCount());
    const maxStart = Math.max(0, cards.length - visibleCount);
    startIndex = Math.min(startIndex, maxStart);
    const cardWidth = cards[0].getBoundingClientRect().width;
    const styles = window.getComputedStyle(track);
    const gap = parseFloat(styles.columnGap || styles.gap || "0");
    const offset = (cardWidth + gap) * startIndex;
    track.style.transform = `translateX(-${offset}px)`;
    track.style.transition = prefersReducedMotion ? "none" : "transform 420ms ease";

    prevBtn.disabled = startIndex <= 0;
    nextBtn.disabled = startIndex >= maxStart;
    prevBtn.classList.toggle("is-disabled", prevBtn.disabled);
    nextBtn.classList.toggle("is-disabled", nextBtn.disabled);
  };

  prevBtn.addEventListener("click", () => {
    startIndex = Math.max(0, startIndex - 1);
    update();
  });

  nextBtn.addEventListener("click", () => {
    const visibleCount = Math.max(1, getVisibleCount());
    const maxStart = Math.max(0, cards.length - visibleCount);
    startIndex = Math.min(maxStart, startIndex + 1);
    update();
  });

  window.addEventListener("resize", update);
  update();
};

const initTourDaysAccordion = () => {
  const cards = Array.from(document.querySelectorAll(".tour-day-card"));
  if (cards.length === 0) return;

  cards.forEach((card) => {
    const toggle = card.querySelector(".tour-day-toggle");
    const icon = card.querySelector(".tour-day-toggle-icon");
    const body = card.querySelector(".tour-day-body");
    if (!toggle || !icon || !body) return;

    card.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
    icon.textContent = "+";

    toggle.addEventListener("click", () => {
      const willOpen = !card.classList.contains("is-open");
      card.classList.toggle("is-open", willOpen);
      toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
      icon.textContent = willOpen ? "−" : "+";
    });

    const gallery = card.querySelector(".tour-day-images");
    const prevBtn = card.querySelector(".tour-day-gallery-prev");
    const nextBtn = card.querySelector(".tour-day-gallery-next");
    if (!gallery || !prevBtn || !nextBtn) return;

    const step = () => {
      const first = gallery.querySelector(".tour-day-image");
      if (!first) return 240;
      const width = first.getBoundingClientRect().width;
      const styles = window.getComputedStyle(gallery);
      const gap = parseFloat(styles.columnGap || styles.gap || "0");
      return width + gap;
    };

    const updateButtons = () => {
      const maxScroll = gallery.scrollWidth - gallery.clientWidth;
      const left = gallery.scrollLeft;
      const canPrev = left > 2;
      const canNext = left < maxScroll - 2;
      prevBtn.disabled = !canPrev;
      nextBtn.disabled = !canNext;
      const wrapper = card.querySelector(".tour-day-gallery");
      if (wrapper) {
        wrapper.classList.toggle("has-prev", canPrev);
        wrapper.classList.toggle("has-next", canNext);
      }
    };

    prevBtn.addEventListener("click", () => {
      gallery.scrollBy({ left: -step(), behavior: "smooth" });
    });

    nextBtn.addEventListener("click", () => {
      gallery.scrollBy({ left: step(), behavior: "smooth" });
    });

    gallery.addEventListener("scroll", updateButtons, { passive: true });
    window.addEventListener("resize", updateButtons);
    updateButtons();
  });
};

document.addEventListener("DOMContentLoaded", () => {
  initHeroTabs();
  initTourNavTabs();
  initPeoplePicker();
  initHotelDateFields();
  initWhereFieldAutoGrow();
  initAuthModal();
  initContactsModal();
  initAuthLogout();
  initJournalSlider();
  initDagestanSlider();
  initHotToursSlider();
  initTourDaysAccordion();
});
