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
  initJournalSlider();
  initDagestanSlider();
  initHotToursSlider();
  initTourDaysAccordion();
});
