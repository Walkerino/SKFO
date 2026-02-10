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

const initPeoplePicker = () => {
  const field = document.querySelector(".hero-field-people");
  if (!field) return;

  const input = field.querySelector("input");
  const popover = field.querySelector(".people-popover");
  const countEl = field.querySelector(".people-count");
  const minusBtn = field.querySelector("[data-action='minus']");
  const plusBtn = field.querySelector("[data-action='plus']");
  if (!input || !popover || !countEl || !minusBtn || !plusBtn) return;

  let count = 1;

  const formatLabel = (value) => {
    if (value % 10 === 1 && value % 100 !== 11) return `${value} человек`;
    if (value % 10 >= 2 && value % 10 <= 4 && (value % 100 < 10 || value % 100 >= 20)) {
      return `${value} человека`;
    }
    return `${value} человек`;
  };

  const setCount = (value) => {
    count = Math.max(1, value);
    countEl.textContent = String(count);
    input.value = formatLabel(count);
  };

  const open = () => {
    popover.classList.add("is-open");
    popover.setAttribute("aria-hidden", "false");
  };

  const close = () => {
    popover.classList.remove("is-open");
    popover.setAttribute("aria-hidden", "true");
  };

  setCount(count);

  input.addEventListener("click", (event) => {
    event.stopPropagation();
    open();
  });

  field.addEventListener("click", (event) => {
    if (event.target === input) return;
    event.stopPropagation();
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

  document.addEventListener("click", () => {
    close();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") close();
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

document.addEventListener("DOMContentLoaded", () => {
  initHeroTabs();
  initPeoplePicker();
  initJournalSlider();
});
