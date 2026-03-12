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
  const closeOtherPickers = (activeField) => {
    pickers.forEach((picker) => {
      if (picker.field !== activeField) picker.close();
    });
  };

  fields.forEach((field) => {
    const input = field.querySelector('input:not([type="hidden"])');
    const hiddenTotalInput =
      field.querySelector("[data-people-hidden-total]") || field.querySelector('input[type="hidden"]');
    const popover = field.querySelector(".people-popover");
    if (!input || !popover) return;

    const min = Math.max(1, Number(field.dataset.peopleMin) || 1);
    const rawMax = Number(field.dataset.peopleMax);
    const max = Number.isFinite(rawMax) && rawMax >= min ? rawMax : 50;
    const singular = field.dataset.peopleUnitSingular || "человек";
    const few = field.dataset.peopleUnitFew || "человека";
    const many = field.dataset.peopleUnitMany || "человек";

    const open = () => {
      popover.classList.add("is-open");
      popover.setAttribute("aria-hidden", "false");
    };

    const close = () => {
      popover.classList.remove("is-open");
      popover.setAttribute("aria-hidden", "true");
    };

    pickers.push({ field, open, close, isOpen: () => popover.classList.contains("is-open") });

    const categoryRows = Array.from(popover.querySelectorAll("[data-people-category]"));
    if (categoryRows.length > 0) {
      const categories = categoryRows
        .map((row) => {
          const key = String(row.getAttribute("data-people-category") || "").trim();
          const countEl = row.querySelector("[data-people-count]");
          if (!key || !(countEl instanceof HTMLElement)) return null;

          const hiddenInput = field.querySelector(`[data-people-hidden="${key}"]`);
          const minValue = Math.max(0, Number(row.getAttribute("data-min")) || 0);
          const rowMaxRaw = Number(row.getAttribute("data-max"));
          const maxValue = Number.isFinite(rowMaxRaw) && rowMaxRaw >= minValue ? rowMaxRaw : max;
          const singularUnit = row.getAttribute("data-unit-singular") || key;
          const fewUnit = row.getAttribute("data-unit-few") || singularUnit;
          const manyUnit = row.getAttribute("data-unit-many") || fewUnit;

          const hiddenValue =
            hiddenInput instanceof HTMLInputElement ? Number(hiddenInput.value || "") : Number.NaN;
          const countValue = Number(String(countEl.textContent || "").replace(/\D+/g, ""));
          let initial = Number.isFinite(hiddenValue) ? hiddenValue : countValue;
          if (!Number.isFinite(initial)) initial = minValue;
          initial = Math.max(minValue, Math.min(maxValue, initial));

          return {
            key,
            countEl,
            hiddenInput: hiddenInput instanceof HTMLInputElement ? hiddenInput : null,
            min: minValue,
            max: maxValue,
            singularUnit,
            fewUnit,
            manyUnit,
            value: initial,
          };
        })
        .filter(Boolean);

      if (categories.length === 0) return;

      const sumCategories = () =>
        categories.reduce((sum, category) => sum + Math.max(category.min, category.value), 0);

      const normalizeTotals = () => {
        categories.forEach((category) => {
          category.value = Math.max(category.min, Math.min(category.max, category.value));
        });

        let total = sumCategories();
        if (total < min) {
          const firstCategory = categories[0];
          if (firstCategory) {
            const missing = min - total;
            firstCategory.value = Math.min(firstCategory.max, firstCategory.value + missing);
          }
        }

        total = sumCategories();
        if (total > max) {
          let overflow = total - max;
          for (let index = categories.length - 1; index >= 0 && overflow > 0; index -= 1) {
            const category = categories[index];
            const allowedDecrease = Math.max(0, category.value - category.min);
            if (allowedDecrease <= 0) continue;
            const delta = Math.min(allowedDecrease, overflow);
            category.value -= delta;
            overflow -= delta;
          }
        }
      };

      const syncCategories = () => {
        const previousInputValue = input.value;
        const previousTotalValue =
          hiddenTotalInput instanceof HTMLInputElement ? hiddenTotalInput.value : "";
        normalizeTotals();
        const total = sumCategories();
        const labelParts = [];

        categories.forEach((category) => {
          category.countEl.textContent = String(category.value);
          if (category.hiddenInput) category.hiddenInput.value = String(category.value);
          if (category.value > 0) {
            labelParts.push(
              formatLabel(category.value, category.singularUnit, category.fewUnit, category.manyUnit),
            );
          }
        });

        if (hiddenTotalInput instanceof HTMLInputElement) {
          hiddenTotalInput.value = String(total);
        }

        input.value =
          labelParts.length > 0 ? labelParts.join(", ") : formatLabel(total, singular, few, many);

        if (input.value !== previousInputValue) {
          input.dispatchEvent(new Event("change", { bubbles: true }));
        }
        if (
          hiddenTotalInput instanceof HTMLInputElement &&
          hiddenTotalInput.value !== previousTotalValue
        ) {
          hiddenTotalInput.dispatchEvent(new Event("change", { bubbles: true }));
        }
      };

      const changeCategory = (key, action) => {
        const category = categories.find((item) => item.key === key);
        if (!category) return;

        const currentTotal = sumCategories();
        if (action === "plus") {
          if (category.value >= category.max || currentTotal >= max) return;
          category.value += 1;
          syncCategories();
          return;
        }

        if (action === "minus") {
          if (category.value <= category.min || currentTotal <= min) return;
          category.value -= 1;
          syncCategories();
        }
      };

      syncCategories();

      popover.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest("[data-action][data-people-target]");
        if (!(button instanceof HTMLElement)) return;
        event.stopPropagation();
        const action = button.getAttribute("data-action");
        const targetKey = button.getAttribute("data-people-target") || "";
        if (action !== "plus" && action !== "minus") return;
        changeCategory(targetKey, action);
      });
    } else {
      const countEl = field.querySelector(".people-count");
      const minusBtn = field.querySelector("[data-action='minus']");
      const plusBtn = field.querySelector("[data-action='plus']");
      if (
        !(countEl instanceof HTMLElement) ||
        !(minusBtn instanceof HTMLElement) ||
        !(plusBtn instanceof HTMLElement)
      ) {
        return;
      }

      const valueFromInput = Number(String(input.value || "").replace(/\D+/g, ""));
      const valueFromHidden =
        hiddenTotalInput instanceof HTMLInputElement ? Number(hiddenTotalInput.value) : Number.NaN;
      let count = Number.isFinite(valueFromHidden) && valueFromHidden > 0 ? valueFromHidden : valueFromInput;
      if (!Number.isFinite(count) || count < min) count = min;
      if (count > max) count = max;

      const setCount = (value) => {
        const previousInputValue = input.value;
        const previousTotalValue =
          hiddenTotalInput instanceof HTMLInputElement ? hiddenTotalInput.value : "";
        count = Math.max(min, Math.min(max, value));
        countEl.textContent = String(count);
        input.value = formatLabel(count, singular, few, many);
        if (hiddenTotalInput instanceof HTMLInputElement) hiddenTotalInput.value = String(count);

        if (input.value !== previousInputValue) {
          input.dispatchEvent(new Event("change", { bubbles: true }));
        }
        if (
          hiddenTotalInput instanceof HTMLInputElement &&
          hiddenTotalInput.value !== previousTotalValue
        ) {
          hiddenTotalInput.dispatchEvent(new Event("change", { bubbles: true }));
        }
      };

      setCount(count);

      minusBtn.addEventListener("click", (event) => {
        event.stopPropagation();
        setCount(count - 1);
      });

      plusBtn.addEventListener("click", (event) => {
        event.stopPropagation();
        setCount(count + 1);
      });
    }

    input.addEventListener("click", (event) => {
      event.stopPropagation();
      closeOtherPickers(field);
      open();
    });

    input.addEventListener("focus", (event) => {
      event.stopPropagation();
      closeOtherPickers(field);
      open();
    });

    field.addEventListener("click", (event) => {
      if (event.target instanceof Element && event.target.closest(".people-popover")) return;
      event.stopPropagation();
      closeOtherPickers(field);
      open();
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
  const hotelDateInputs = Array.from(document.querySelectorAll("input[data-hotel-date]"));
  const legacyDateInputs = Array.from(
    document.querySelectorAll("input[data-date-input]:not([data-hotel-date])"),
  );
  if (hotelDateInputs.length === 0 && legacyDateInputs.length === 0) return;

  const isValidDate = (year, month, day) => {
    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return false;
    if (year < 1000 || year > 9999) return false;
    if (month < 1 || month > 12) return false;
    if (day < 1 || day > 31) return false;

    const date = new Date(year, month - 1, day);
    return (
      date.getFullYear() === year &&
      date.getMonth() === month - 1 &&
      date.getDate() === day
    );
  };

  const parseIsoDate = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || "").trim());
    if (!match) return "";
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (!isValidDate(year, month, day)) return "";
    return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
  };

  const parseRuDate = (value) => {
    const match = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(String(value || "").trim());
    if (!match) return "";
    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    if (!isValidDate(year, month, day)) return "";
    return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
  };

  const parseAnyDate = (value) => parseIsoDate(value) || parseRuDate(value);

  const formatRuDate = (isoValue) => {
    const iso = parseIsoDate(isoValue);
    if (!iso) return "";
    const [year, month, day] = iso.split("-");
    return `${day}.${month}.${year}`;
  };

  if (hotelDateInputs.length > 0) {
    const monthLabelFormatter = new Intl.DateTimeFormat("ru-RU", {
      month: "long",
      year: "numeric",
    });
    const weekdayLabels = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];
    const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);

    const toIsoDate = (date) =>
      `${String(date.getFullYear()).padStart(4, "0")}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(
        date.getDate(),
      ).padStart(2, "0")}`;

    const isoToDate = (isoValue) => {
      const iso = parseIsoDate(isoValue);
      if (!iso) return null;
      const [year, month, day] = iso.split("-").map(Number);
      return new Date(year, month - 1, day);
    };

    const todayIso = toIsoDate(new Date());
    const pickers = [];

    const closeAll = (exceptInput = null) => {
      pickers.forEach((picker) => {
        if (exceptInput && picker.input === exceptInput) return;
        picker.close();
      });
    };

    const setInputIso = (input, isoValue) => {
      const normalized = parseIsoDate(isoValue);
      const previousIso = parseIsoDate(input.dataset.hotelDateIso || "") || parseAnyDate(input.value || "");
      if (normalized) {
        input.value = formatRuDate(normalized);
        input.dataset.hotelDateIso = normalized;
      } else {
        input.value = "";
        delete input.dataset.hotelDateIso;
      }
      if ((normalized || "") !== (previousIso || "")) {
        input.dispatchEvent(new Event("change", { bubbles: true }));
      }
    };

    const getInputIso = (input) => {
      const fromDataset = parseIsoDate(input.dataset.hotelDateIso || "");
      if (fromDataset) return fromDataset;
      return parseAnyDate(input.value || "");
    };

    hotelDateInputs.forEach((input) => {
      const field = input.closest(".hotel-booking-input--date");
      if (!(field instanceof HTMLElement)) return;

      let selectedIso = getInputIso(input);
      setInputIso(input, selectedIso);
      let viewMonth = isoToDate(selectedIso) || new Date();
      viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1);

      const popover = document.createElement("div");
      popover.className = "date-range-popover hotel-date-popover";
      popover.setAttribute("aria-hidden", "true");
      popover.innerHTML = `
        <div class="date-range-head">
          <button class="date-range-nav-btn" type="button" data-hotel-date-action="prev" aria-label="Предыдущий месяц">‹</button>
          <div class="date-range-month" data-hotel-date-month></div>
          <button class="date-range-nav-btn" type="button" data-hotel-date-action="next" aria-label="Следующий месяц">›</button>
        </div>
        <div class="date-range-weekdays">
          ${weekdayLabels.map((label) => `<span>${label}</span>`).join("")}
        </div>
        <div class="date-range-grid" data-hotel-date-grid></div>
        <div class="date-range-actions">
          <button class="date-range-btn date-range-btn--muted" type="button" data-hotel-date-action="clear">Очистить</button>
          <button class="date-range-btn" type="button" data-hotel-date-action="today">Сегодня</button>
        </div>
      `;
      field.appendChild(popover);

      const monthEl = popover.querySelector("[data-hotel-date-month]");
      const gridEl = popover.querySelector("[data-hotel-date-grid]");
      if (!(monthEl instanceof HTMLElement) || !(gridEl instanceof HTMLElement)) return;

      const getPairInput = () => {
        const pairName = String(input.dataset.hotelDatePair || "").trim();
        if (!pairName) return null;
        return (
          hotelDateInputs.find(
            (candidate) =>
              candidate !== input && String(candidate.getAttribute("name") || "").trim() === pairName,
          ) || null
        );
      };

      const syncPairBounds = () => {
        const role = String(input.dataset.hotelDateRole || "").trim();
        const pairInput = getPairInput();
        if (!pairInput) return;

        const currentIso = getInputIso(input);
        const pairIso = getInputIso(pairInput);
        if (!currentIso || !pairIso) return;

        if (role === "start" && currentIso > pairIso) {
          setInputIso(pairInput, currentIso);
        } else if (role === "end" && currentIso < pairIso) {
          setInputIso(pairInput, currentIso);
        }
      };

      const renderMonth = () => {
        monthEl.textContent = capitalize(monthLabelFormatter.format(viewMonth));
        gridEl.innerHTML = "";

        const firstDay = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1);
        const lastDay = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 0);
        const offset = (firstDay.getDay() + 6) % 7;

        for (let index = 0; index < offset; index += 1) {
          const emptyCell = document.createElement("span");
          emptyCell.className = "date-range-day-empty";
          gridEl.appendChild(emptyCell);
        }

        for (let day = 1; day <= lastDay.getDate(); day += 1) {
          const date = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), day);
          const iso = toIsoDate(date);
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "date-range-day";
          btn.dataset.iso = iso;
          btn.textContent = String(day);
          if (iso === todayIso) btn.classList.add("is-today");
          if (selectedIso && iso === selectedIso) btn.classList.add("is-selected");
          gridEl.appendChild(btn);
        }
      };

      const open = () => {
        selectedIso = getInputIso(input);
        const selectedDate = isoToDate(selectedIso);
        if (selectedDate) {
          viewMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
        }
        renderMonth();
        closeAll(input);
        popover.classList.add("is-open");
        popover.setAttribute("aria-hidden", "false");
      };

      const close = () => {
        popover.classList.remove("is-open");
        popover.setAttribute("aria-hidden", "true");
      };

      const applyIso = (isoValue, shouldClose = true) => {
        const normalized = parseIsoDate(isoValue);
        selectedIso = normalized;
        setInputIso(input, normalized);
        syncPairBounds();
        renderMonth();
        if (shouldClose) close();
      };

      pickers.push({
        input,
        field,
        close,
        isOpen: () => popover.classList.contains("is-open"),
      });

      popover.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) return;

        const actionBtn = event.target.closest("[data-hotel-date-action]");
        if (actionBtn instanceof HTMLElement) {
          const action = actionBtn.dataset.hotelDateAction || "";
          if (action === "prev") {
            viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() - 1, 1);
            renderMonth();
          } else if (action === "next") {
            viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 1);
            renderMonth();
          } else if (action === "clear") {
            selectedIso = "";
            setInputIso(input, "");
            renderMonth();
            close();
          } else if (action === "today") {
            viewMonth = new Date();
            viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1);
            applyIso(todayIso, true);
          }
          return;
        }

        const dayBtn = event.target.closest(".date-range-day");
        if (!(dayBtn instanceof HTMLElement)) return;
        const iso = parseIsoDate(dayBtn.dataset.iso || "");
        if (!iso) return;
        applyIso(iso, true);
      });

      input.addEventListener("click", (event) => {
        event.stopPropagation();
        open();
      });

      input.addEventListener("focus", () => {
        open();
      });

      field.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) return;
        if (event.target.closest(".hotel-date-popover")) return;
        event.stopPropagation();
        open();
      });

      renderMonth();
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
  }

  legacyDateInputs.forEach((input) => {
    const toTextMode = () => {
      input.type = "text";
      const value = input.value.trim();
      if (!value) return;
      const iso = parseAnyDate(value);
      if (!iso) return;
      input.value = formatRuDate(iso);
    };

    const toDateMode = () => {
      const value = input.value.trim();
      const iso = parseAnyDate(value);
      input.type = "date";
      input.value = iso;
    };

    toTextMode();

    const setTextMode = () => {
      window.requestAnimationFrame(toTextMode);
    };

    const openPicker = () => {
      if (input.type !== "date") toDateMode();
      input.focus();
      if (typeof input.showPicker === "function") {
        try {
          input.showPicker();
        } catch (error) {
          // Ignore unsupported browsers.
        }
      }
    };

    input.addEventListener("focus", () => {
      if (input.type !== "date") toDateMode();
    });

    input.addEventListener("click", () => {
      openPicker();
    });

    const field = input.closest(".hero-field");
    if (field) {
      field.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) return;
        if (event.target === input || event.target.closest("input") === input) return;
        openPicker();
      });
    }

    input.addEventListener("change", toTextMode);
    input.addEventListener("blur", setTextMode);
  });
};

const initHotelRoomsSearchControls = () => {
  const summary = document.querySelector("[data-hotel-booking-summary]");
  const actionBtn = document.querySelector("[data-hotel-booking-action]");
  const checkInInput = document.querySelector("input[data-hotel-booking-check-in]");
  const checkOutInput = document.querySelector("input[data-hotel-booking-check-out]");
  const guestsTotalInput = document.querySelector("input[name='guests_total'][data-people-hidden-total]");
  const roomList = document.querySelector("[data-hotel-room-list]");
  const roomRows = Array.from(document.querySelectorAll("[data-hotel-room-row]"));
  const offerCards = Array.from(document.querySelectorAll("[data-room-offer-card]"));
  const emptyState = document.querySelector("[data-hotel-rooms-empty]");
  const emptyTitle = document.querySelector("[data-hotel-rooms-empty-title]");
  const emptySubtitle = document.querySelector("[data-hotel-rooms-empty-subtitle]");

  if (
    !(summary instanceof HTMLElement) ||
    !(actionBtn instanceof HTMLButtonElement) ||
    !(checkInInput instanceof HTMLInputElement) ||
    !(checkOutInput instanceof HTMLInputElement) ||
    !(guestsTotalInput instanceof HTMLInputElement) ||
    !(roomList instanceof HTMLElement)
  ) {
    return;
  }

  const hasRooms = roomRows.length > 0;
  const emptyTitleHotel = (
    emptyState instanceof HTMLElement ? String(emptyState.dataset.emptyTitleHotel || "").trim() : ""
  ) || "Нет свободных номеров на данный момент, приносим свои извинения";
  const emptyTitleSearch = (
    emptyState instanceof HTMLElement ? String(emptyState.dataset.emptyTitleSearch || "").trim() : ""
  ) || "Нет свободных номеров на ваши даты";
  const emptySubtitleSearch = (
    emptyState instanceof HTMLElement ? String(emptyState.dataset.emptySubtitleSearch || "").trim() : ""
  ) || "Попробуйте сменить параметры или выбрать другой отель.";

  const setEmptyState = (mode, isVisible) => {
    if (!(emptyState instanceof HTMLElement)) return;
    emptyState.hidden = !isVisible;
    if (!isVisible) return;

    if (emptyTitle instanceof HTMLElement) {
      emptyTitle.textContent = mode === "hotel" ? emptyTitleHotel : emptyTitleSearch;
    }
    if (emptySubtitle instanceof HTMLElement) {
      emptySubtitle.textContent = emptySubtitleSearch;
      emptySubtitle.hidden = mode !== "search";
    }
  };

  const parseIsoDate = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || "").trim());
    if (!match) return "";
    return `${match[1]}-${match[2]}-${match[3]}`;
  };

  const parseRuDate = (value) => {
    const match = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(String(value || "").trim());
    if (!match) return "";
    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    if (!Number.isInteger(day) || !Number.isInteger(month) || !Number.isInteger(year)) return "";
    if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1000 || year > 9999) return "";
    const date = new Date(year, month - 1, day);
    if (
      date.getFullYear() !== year ||
      date.getMonth() !== month - 1 ||
      date.getDate() !== day
    ) {
      return "";
    }
    return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
  };

  const resolveIso = (input) => parseIsoDate(input.dataset.hotelDateIso || "") || parseRuDate(input.value || "");

  const formatLabel = (value, singular, few, many) => {
    const safeValue = Math.max(0, Number(value) || 0);
    const mod10 = safeValue % 10;
    const mod100 = safeValue % 100;
    if (mod10 === 1 && mod100 !== 11) return `${safeValue} ${singular}`;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return `${safeValue} ${few}`;
    return `${safeValue} ${many}`;
  };

  const formatOfferPriceLabel = (value, prefix = "") => {
    const safeValue = Math.max(0, Math.round(Number(value) || 0));
    const amount = safeValue.toLocaleString("en-US");
    return `${prefix}₽ ${amount}`;
  };

  const getNights = (checkInIso, checkOutIso) => {
    if (!checkInIso || !checkOutIso) return 0;
    const [checkInYear, checkInMonth, checkInDay] = checkInIso.split("-").map(Number);
    const [checkOutYear, checkOutMonth, checkOutDay] = checkOutIso.split("-").map(Number);
    const checkInMs = Date.UTC(checkInYear, checkInMonth - 1, checkInDay);
    const checkOutMs = Date.UTC(checkOutYear, checkOutMonth - 1, checkOutDay);
    const diffDays = Math.round((checkOutMs - checkInMs) / 86400000);
    return Math.max(1, diffDays || 0);
  };

  let hasSearched = false;

  const getState = () => {
    const checkInIso = resolveIso(checkInInput);
    const checkOutIso = resolveIso(checkOutInput);
    const guestsTotal = Math.max(0, Number(guestsTotalInput.value) || 0);
    const hasDates = Boolean(checkInIso && checkOutIso);
    const isReady = hasDates && guestsTotal > 0;
    const nights = isReady ? getNights(checkInIso, checkOutIso) : 0;
    return {
      checkInIso,
      checkOutIso,
      guestsTotal,
      isReady,
      nights,
    };
  };

  const syncOfferCards = (state) => {
    if (!offerCards.length) return;
    const hasDateRange = Boolean(state.checkInIso && state.checkOutIso);
    const selectedNights = hasDateRange ? Math.max(1, state.nights) : 1;

    offerCards.forEach((offerCard) => {
      if (!(offerCard instanceof HTMLElement)) return;
      const priceNode = offerCard.querySelector("[data-room-offer-price]");
      const captionNode = offerCard.querySelector("[data-room-offer-caption]");
      if (!(priceNode instanceof HTMLElement) || !(captionNode instanceof HTMLElement)) return;

      const ratePerGuest = Math.max(0, Number(offerCard.dataset.offerRatePerGuest) || 0);
      if (ratePerGuest <= 0) return;

      const defaultGuests = Math.max(1, Number(offerCard.dataset.offerDefaultGuests) || 1);
      const selectedGuests = Math.max(1, Number(state.guestsTotal) || defaultGuests);
      const totalPrice = ratePerGuest * selectedGuests * selectedNights;
      const pricePrefix = String(offerCard.dataset.offerPricePrefix || "");

      priceNode.textContent = formatOfferPriceLabel(totalPrice, pricePrefix);
      const nightsLabel = formatLabel(selectedNights, "ночь", "ночи", "ночей");
      const guestsLabel = formatLabel(selectedGuests, "гость", "гостя", "гостей");
      captionNode.textContent = `за ${nightsLabel}, для ${guestsLabel}`;
    });
  };

  const isOfferAvailableForState = (offerCard, state, roomMaxGuests) => {
    if (!(offerCard instanceof HTMLElement)) return false;

    const guests = Math.max(1, Number(state.guestsTotal) || 1);
    const nights = Math.max(1, Number(state.nights) || 1);
    if (guests > roomMaxGuests) return false;

    const isClosed = String(offerCard.dataset.offerClosed || "0") === "1";
    if (isClosed) return false;

    const minGuests = Math.max(0, Number(offerCard.dataset.offerMinGuests) || 0);
    const maxGuests = Math.max(0, Number(offerCard.dataset.offerMaxGuests) || 0);
    const minNights = Math.max(0, Number(offerCard.dataset.offerMinNights) || 0);
    const maxNights = Math.max(0, Number(offerCard.dataset.offerMaxNights) || 0);

    if (minGuests > 0 && guests < minGuests) return false;
    if (maxGuests > 0 && guests > maxGuests) return false;
    if (minNights > 0 && nights < minNights) return false;
    if (maxNights > 0 && nights > maxNights) return false;

    const offerDateFrom = String(offerCard.dataset.offerDateFrom || "").trim();
    const offerDateTo = String(offerCard.dataset.offerDateTo || "").trim();
    if (offerDateFrom && state.checkInIso < offerDateFrom) return false;
    if (offerDateTo && state.checkOutIso > offerDateTo) return false;

    return true;
  };

  const syncUi = () => {
    const state = getState();
    actionBtn.textContent = hasSearched ? "Обновить" : "Найти";
    syncOfferCards(state);

    if (!hasRooms) {
      actionBtn.disabled = true;
      summary.hidden = true;
      roomList.hidden = true;
      setEmptyState("hotel", true);
      return;
    }

    actionBtn.disabled = !state.isReady;

    if (!state.isReady) {
      summary.hidden = true;
    } else {
      const nightsLabel = formatLabel(state.nights, "ночь", "ночи", "ночей");
      const guestsLabel = formatLabel(state.guestsTotal, "гость", "гостя", "гостей");
      summary.textContent = `На ${nightsLabel}, ${guestsLabel}`;
      summary.hidden = false;
    }

    if (!hasSearched || !state.isReady) {
      roomRows.forEach((row) => {
        row.hidden = false;
        const rowOfferCards = Array.from(row.querySelectorAll("[data-room-offer-card]"));
        rowOfferCards.forEach((offerCard) => {
          offerCard.hidden = false;
        });
      });
      roomList.hidden = false;
      setEmptyState("search", false);
      window.dispatchEvent(new Event("resize"));
      return;
    }

    let visibleRowsCount = 0;
    roomRows.forEach((row) => {
      const roomMaxGuests = Math.max(1, Number(row.dataset.roomMaxGuests) || 1);
      const rowOfferCards = Array.from(row.querySelectorAll("[data-room-offer-card]"));
      let visibleOffersCount = 0;
      rowOfferCards.forEach((offerCard) => {
        const isVisible = isOfferAvailableForState(offerCard, state, roomMaxGuests);
        offerCard.hidden = !isVisible;
        if (isVisible) visibleOffersCount += 1;
      });

      const hasVisibleOffers = visibleOffersCount > 0;
      row.hidden = !hasVisibleOffers;
      if (hasVisibleOffers) visibleRowsCount += 1;
    });

    const hasVisibleRows = visibleRowsCount > 0;
    roomList.hidden = !hasVisibleRows;
    setEmptyState("search", !hasVisibleRows);
    window.dispatchEvent(new Event("resize"));
  };

  const onSearch = () => {
    const state = getState();
    if (!state.isReady) {
      syncUi();
      return;
    }
    hasSearched = true;
    syncUi();
  };

  const watchInputs = [checkInInput, checkOutInput, guestsTotalInput];
  watchInputs.forEach((input) => {
    input.addEventListener("change", syncUi);
    input.addEventListener("input", syncUi);
  });

  actionBtn.addEventListener("click", onSearch);
  syncUi();
};

const initDateRangeFields = () => {
  const rangeInputs = Array.from(document.querySelectorAll("input[data-date-range-input]"));
  if (rangeInputs.length === 0) return;

  const isValidDate = (year, month, day) => {
    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return false;
    if (year < 1000 || year > 9999) return false;
    if (month < 1 || month > 12) return false;
    if (day < 1 || day > 31) return false;
    const date = new Date(year, month - 1, day);
    return (
      date.getFullYear() === year &&
      date.getMonth() === month - 1 &&
      date.getDate() === day
    );
  };

  const normalizeIsoDate = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || "").trim());
    if (!match) return "";
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (!isValidDate(year, month, day)) return "";
    return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
  };

  const toIsoDate = (date) =>
    `${String(date.getFullYear()).padStart(4, "0")}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(
      date.getDate(),
    ).padStart(2, "0")}`;

  const isoToDate = (iso) => {
    const normalized = normalizeIsoDate(iso);
    if (!normalized) return null;
    const [year, month, day] = normalized.split("-").map(Number);
    return new Date(year, month - 1, day);
  };

  const formatRuDate = (iso) => {
    const normalized = normalizeIsoDate(iso);
    if (!normalized) return "";
    const [, month, day] = normalized.split("-");
    return `${day}.${month}`;
  };

  const monthLabelFormatter = new Intl.DateTimeFormat("ru-RU", {
    month: "long",
    year: "numeric",
  });
  const weekdayLabels = ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"];
  const todayIso = toIsoDate(new Date());
  const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);

  rangeInputs.forEach((input) => {
    const field = input.closest(".hero-field");
    if (!field) return;

    const hiddenFromInput = field.querySelector('input[name="date_from"]');
    const hiddenToInput = field.querySelector('input[name="date_to"]');
    if (!(hiddenFromInput instanceof HTMLInputElement) || !(hiddenToInput instanceof HTMLInputElement)) return;

    let selectedFrom = normalizeIsoDate(input.dataset.dateRangeFrom) || normalizeIsoDate(hiddenFromInput.value);
    let selectedTo = normalizeIsoDate(input.dataset.dateRangeTo) || normalizeIsoDate(hiddenToInput.value);
    if (selectedFrom && !selectedTo) selectedTo = selectedFrom;
    if (!selectedFrom && selectedTo) selectedFrom = selectedTo;
    if (selectedFrom && selectedTo && selectedFrom > selectedTo) {
      const temp = selectedFrom;
      selectedFrom = selectedTo;
      selectedTo = temp;
    }

    const initialViewDate = isoToDate(selectedFrom) || new Date();
    let viewMonth = new Date(initialViewDate.getFullYear(), initialViewDate.getMonth(), 1);

    const popover = document.createElement("div");
    popover.className = "date-range-popover";
    popover.setAttribute("aria-hidden", "true");
    popover.innerHTML = `
      <div class="date-range-head">
        <button class="date-range-nav-btn" type="button" data-date-range-action="prev" aria-label="Предыдущий месяц">‹</button>
        <div class="date-range-month" data-date-range-month></div>
        <button class="date-range-nav-btn" type="button" data-date-range-action="next" aria-label="Следующий месяц">›</button>
      </div>
      <div class="date-range-weekdays">
        ${weekdayLabels.map((label) => `<span>${label}</span>`).join("")}
      </div>
      <div class="date-range-grid" data-date-range-grid></div>
      <div class="date-range-actions">
        <button class="date-range-btn date-range-btn--muted" type="button" data-date-range-action="clear">Очистить</button>
        <button class="date-range-btn" type="button" data-date-range-action="apply">Готово</button>
      </div>
    `;
    field.appendChild(popover);

    const monthEl = popover.querySelector("[data-date-range-month]");
    const gridEl = popover.querySelector("[data-date-range-grid]");
    if (!(monthEl instanceof HTMLElement) || !(gridEl instanceof HTMLElement)) return;

    const syncField = () => {
      hiddenFromInput.value = selectedFrom || "";
      hiddenToInput.value = selectedTo || "";
      input.dataset.dateRangeFrom = selectedFrom || "";
      input.dataset.dateRangeTo = selectedTo || "";

      if (selectedFrom && selectedTo) {
        const fromLabel = formatRuDate(selectedFrom);
        const toLabel = formatRuDate(selectedTo);
        input.value = `${fromLabel}-${toLabel}`;
        field.classList.add("is-filled");
      } else {
        input.value = "";
        field.classList.remove("is-filled");
      }
    };

    const isInRange = (iso) => Boolean(selectedFrom && selectedTo && iso >= selectedFrom && iso <= selectedTo);

    const renderMonth = () => {
      monthEl.textContent = capitalize(monthLabelFormatter.format(viewMonth));
      gridEl.innerHTML = "";

      const firstDay = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1);
      const lastDay = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 0);
      const offset = (firstDay.getDay() + 6) % 7;

      for (let i = 0; i < offset; i += 1) {
        const emptyCell = document.createElement("span");
        emptyCell.className = "date-range-day-empty";
        gridEl.appendChild(emptyCell);
      }

      for (let day = 1; day <= lastDay.getDate(); day += 1) {
        const date = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), day);
        const iso = toIsoDate(date);
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "date-range-day";
        btn.dataset.iso = iso;
        btn.textContent = String(day);
        if (iso === todayIso) btn.classList.add("is-today");
        if (selectedFrom && iso === selectedFrom) btn.classList.add("is-start", "is-selected");
        if (selectedTo && iso === selectedTo) btn.classList.add("is-end", "is-selected");
        if (isInRange(iso) && iso !== selectedFrom && iso !== selectedTo) btn.classList.add("is-in-range");
        gridEl.appendChild(btn);
      }
    };

    const openPopover = () => {
      popover.classList.add("is-open");
      popover.setAttribute("aria-hidden", "false");
    };

    const closePopover = () => {
      popover.classList.remove("is-open");
      popover.setAttribute("aria-hidden", "true");
    };

    const onDaySelect = (iso) => {
      const normalized = normalizeIsoDate(iso);
      if (!normalized) return;

      if (!selectedFrom || (selectedFrom && selectedTo)) {
        selectedFrom = normalized;
        selectedTo = "";
      } else if (normalized < selectedFrom) {
        selectedTo = selectedFrom;
        selectedFrom = normalized;
      } else {
        selectedTo = normalized;
      }

      syncField();
      renderMonth();
    };

    popover.addEventListener("click", (event) => {
      if (!(event.target instanceof Element)) return;

      const action = event.target.closest("[data-date-range-action]");
      if (action instanceof HTMLElement) {
        const actionName = action.dataset.dateRangeAction || "";
        if (actionName === "prev") {
          viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() - 1, 1);
          renderMonth();
        } else if (actionName === "next") {
          viewMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 1);
          renderMonth();
        } else if (actionName === "clear") {
          selectedFrom = "";
          selectedTo = "";
          syncField();
          renderMonth();
        } else if (actionName === "apply") {
          if (selectedFrom && !selectedTo) selectedTo = selectedFrom;
          syncField();
          closePopover();
        }
        return;
      }

      const dayBtn = event.target.closest(".date-range-day");
      if (!(dayBtn instanceof HTMLElement)) return;
      const iso = dayBtn.dataset.iso || "";
      onDaySelect(iso);
    });

    input.addEventListener("click", (event) => {
      event.stopPropagation();
      openPopover();
    });

    field.addEventListener("click", (event) => {
      if (!(event.target instanceof Element)) return;
      if (event.target.closest(".date-range-popover")) return;
      event.stopPropagation();
      openPopover();
    });

    document.addEventListener("click", (event) => {
      if (!(event.target instanceof Node) || !field.contains(event.target)) {
        closePopover();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closePopover();
    });

    syncField();
    renderMonth();
  });
};

const initHeroCustomSelects = () => {
  const selects = Array.from(document.querySelectorAll("select[data-hero-custom-select]"));
  if (selects.length === 0) return;

  const instances = [];

  const closeAll = (exceptField = null) => {
    instances.forEach((instance) => {
      if (exceptField && instance.field === exceptField) return;
      instance.popover.classList.remove("is-open");
      instance.field.classList.remove("is-open");
      instance.popover.setAttribute("aria-hidden", "true");
      instance.trigger.setAttribute("aria-expanded", "false");
    });
  };

  selects.forEach((select, index) => {
    const field = select.closest(".hero-field");
    if (!field) return;
    if (field.querySelector(".hero-custom-select-trigger")) return;

    const trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "hero-custom-select-trigger";
    trigger.setAttribute("aria-haspopup", "listbox");
    trigger.setAttribute("aria-expanded", "false");
    trigger.setAttribute("data-hero-custom-select-trigger", "1");
    trigger.id = `hero-custom-select-trigger-${index + 1}`;

    const popover = document.createElement("div");
    popover.className = "hero-custom-select-popover";
    popover.setAttribute("aria-hidden", "true");

    const list = document.createElement("ul");
    list.className = "hero-custom-select-list";
    list.setAttribute("role", "listbox");
    list.setAttribute("aria-labelledby", trigger.id);
    popover.appendChild(list);

    const rebuildOptions = () => {
      list.innerHTML = "";
      const options = Array.from(select.options);
      options.forEach((option) => {
        const item = document.createElement("li");
        item.className = "hero-custom-select-item";

        const optionBtn = document.createElement("button");
        optionBtn.type = "button";
        optionBtn.className = "hero-custom-select-option";
        optionBtn.setAttribute("role", "option");
        optionBtn.dataset.value = option.value;
        optionBtn.textContent = option.textContent || "";
        if (option.selected) {
          optionBtn.classList.add("is-selected");
          optionBtn.setAttribute("aria-selected", "true");
        } else {
          optionBtn.setAttribute("aria-selected", "false");
        }

        optionBtn.addEventListener("click", () => {
          select.value = option.value;
          select.dispatchEvent(new Event("change", { bubbles: true }));
          closeAll();
        });

        item.appendChild(optionBtn);
        list.appendChild(item);
      });
    };

    const syncFromSelect = () => {
      const selectedOption = select.options[select.selectedIndex] || null;
      trigger.textContent = selectedOption ? selectedOption.textContent || "" : "";

      const selectedValue = String(select.value || "").trim();
      field.classList.toggle("is-filled", selectedValue !== "");

      Array.from(list.querySelectorAll(".hero-custom-select-option")).forEach((btn) => {
        const isSelected = (btn.dataset.value || "") === selectedValue;
        btn.classList.toggle("is-selected", isSelected);
        btn.setAttribute("aria-selected", isSelected ? "true" : "false");
      });
    };

    rebuildOptions();
    syncFromSelect();

    select.classList.add("hero-native-select");
    select.setAttribute("tabindex", "-1");
    select.setAttribute("aria-hidden", "true");

    const icon = field.querySelector("img");
    if (icon && icon.parentElement === field) {
      field.insertBefore(trigger, icon);
    } else {
      field.appendChild(trigger);
    }
    field.appendChild(popover);

    trigger.addEventListener("click", (event) => {
      event.stopPropagation();
      const willOpen = !popover.classList.contains("is-open");
      closeAll(field);
      popover.classList.toggle("is-open", willOpen);
      field.classList.toggle("is-open", willOpen);
      popover.setAttribute("aria-hidden", willOpen ? "false" : "true");
      trigger.setAttribute("aria-expanded", willOpen ? "true" : "false");
    });

    select.addEventListener("change", () => {
      syncFromSelect();
    });

    instances.push({ field, trigger, popover });
  });

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Node)) {
      closeAll();
      return;
    }
    const hasOpenInTarget = instances.some((instance) => instance.field.contains(event.target));
    if (!hasOpenInTarget) closeAll();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    closeAll();
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
  const reCaptchaEnabled = modal.dataset.recaptchaEnabled === "1";
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

  const getCaptchaToken = (form) => {
    const tokenInput = form.querySelector('textarea[name="g-recaptcha-response"]');
    if (!(tokenInput instanceof HTMLTextAreaElement)) return "";
    return String(tokenInput.value || "").trim();
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
      if (reCaptchaEnabled) {
        const captchaToken = getCaptchaToken(form);
        if (!captchaToken) {
          setMessage("Подтвердите, что вы не робот.", "error");
          return;
        }
        payload["g-recaptcha-response"] = captchaToken;
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

      const returnTo = `${window.location.pathname || "/"}${window.location.search || ""}${window.location.hash || ""}`;
      const payload = {
        mode,
        email: emailInput.value.trim(),
        code: codeInput.value.trim(),
        return_to: returnTo,
      };
      if (mode === "register" && nameInput) {
        payload.name = nameInput.value.trim();
      }

      setBusy(form, true);
      try {
        const data = await sendRequest("verify_code", payload);
        setMessage(data.message || "Успешный вход.", "success");
        const redirect = (data.data && data.data.redirect) || returnTo || "/";
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

const initHomeMobileMenu = () => {
  const toggle = document.querySelector("[data-home-menu-toggle]");
  const menu = document.querySelector("[data-home-menu]");
  if (!toggle || !menu) return;

  const setMenuState = (isOpen) => {
    toggle.classList.toggle("is-open", isOpen);
    toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    toggle.setAttribute("aria-label", isOpen ? "Закрыть меню" : "Открыть меню");

    if (isOpen) {
      menu.hidden = false;
      window.requestAnimationFrame(() => {
        menu.classList.add("is-open");
      });
      return;
    }

    menu.classList.remove("is-open");
    window.setTimeout(() => {
      if (!menu.classList.contains("is-open")) {
        menu.hidden = true;
      }
    }, 160);
  };

  const closeMenu = () => setMenuState(false);

  toggle.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    const isOpen = toggle.getAttribute("aria-expanded") === "true";
    setMenuState(!isOpen);
  });

  menu.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;
    if (event.target.closest("a, button")) {
      closeMenu();
    }
  });

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Node)) return;
    if (!toggle.contains(event.target) && !menu.contains(event.target)) {
      closeMenu();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });

  const desktopMedia = window.matchMedia("(min-width: 721px)");
  const handleDesktopViewport = () => {
    if (desktopMedia.matches) {
      closeMenu();
    }
  };

  if (typeof desktopMedia.addEventListener === "function") {
    desktopMedia.addEventListener("change", handleDesktopViewport);
  } else if (typeof desktopMedia.addListener === "function") {
    desktopMedia.addListener(handleDesktopViewport);
  }
  handleDesktopViewport();
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

const initProfileEditor = () => {
  const root = document.querySelector("[data-profile-editor]");
  if (!root) return;

  const nameEl = root.querySelector("[data-profile-name]");
  const nameValueEl = root.querySelector("[data-profile-name-value]");
  const descriptionEl = root.querySelector("[data-profile-description]");
  const initialsEl = root.querySelector("[data-profile-avatar-initials]");
  const avatarImageEl = root.querySelector("[data-profile-avatar-image]");
  const avatarInputEl = root.querySelector("[data-profile-avatar-input]");
  const form = root.querySelector("[data-profile-form]");
  const nameInputEl = root.querySelector("[data-profile-input-name]");
  const descriptionInputEl = root.querySelector("[data-profile-input-description]");
  const saveBtnEl = root.querySelector("[data-profile-save]");
  const messageEl = root.querySelector("[data-profile-message]");

  if (
    !(nameEl instanceof HTMLElement) ||
    !(nameValueEl instanceof HTMLElement) ||
    !(descriptionEl instanceof HTMLElement) ||
    !(initialsEl instanceof HTMLElement) ||
    !(avatarImageEl instanceof HTMLImageElement) ||
    !(avatarInputEl instanceof HTMLInputElement) ||
    !(form instanceof HTMLFormElement) ||
    !(nameInputEl instanceof HTMLInputElement) ||
    !(descriptionInputEl instanceof HTMLTextAreaElement)
  ) {
    return;
  }

  const apiUrl = String(root.getAttribute("data-profile-api-url") || window.location.pathname || "/");
  const csrfName = String(root.getAttribute("data-csrf-name") || "").trim();
  const csrfValue = String(root.getAttribute("data-csrf-value") || "").trim();
  const defaultName = String(root.getAttribute("data-profile-default-name") || "")
    .replace(/\s+/g, " ")
    .trim();
  const fallbackName = defaultName || "Путешественник";
  const defaultBio = String(root.getAttribute("data-profile-default-bio") || "").trim();
  const defaultAvatar = String(root.getAttribute("data-profile-default-avatar") || "").trim();
  const defaultInitials = String(root.getAttribute("data-profile-default-initials") || "SK").trim();
  const maxAvatarBytes = 2 * 1024 * 1024;

  const buildInitials = (value) => {
    const source = String(value || "").replace(/\s+/g, " ").trim();
    if (!source) return defaultInitials || "SK";
    const parts = source.split(" ").filter(Boolean);
    const letters = parts.map((part) => part.slice(0, 1).toUpperCase()).slice(0, 2);
    if (letters.length === 0) return defaultInitials || "SK";
    return letters.join("");
  };

  const setMessage = (text, isError = false) => {
    if (!(messageEl instanceof HTMLElement)) return;
    messageEl.textContent = text;
    messageEl.style.color = isError ? "#b12828" : "#206f44";
  };

  const safeName = (value) => {
    const normalized = String(value || "").replace(/\s+/g, " ").trim();
    return normalized || fallbackName;
  };

  const safeDescription = (value) => String(value || "").trim().slice(0, 240);

  const render = (state) => {
    const currentName = safeName(state.name);
    const currentDescription = safeDescription(state.description);
    const currentAvatar = String(state.avatar || "").trim();

    nameEl.textContent = currentName;
    nameValueEl.textContent = currentName;
    descriptionEl.textContent = currentDescription || "Добавьте описание профиля.";
    nameInputEl.value = currentName;
    descriptionInputEl.value = currentDescription;
    initialsEl.textContent = buildInitials(currentName);

    if (currentAvatar) {
      avatarImageEl.src = currentAvatar;
      avatarImageEl.hidden = false;
      initialsEl.hidden = true;
    } else {
      avatarImageEl.removeAttribute("src");
      avatarImageEl.hidden = true;
      initialsEl.hidden = false;
    }
  };

  const setBusy = (busy) => {
    if (saveBtnEl instanceof HTMLButtonElement) {
      saveBtnEl.disabled = busy;
      saveBtnEl.textContent = busy ? "Сохранение..." : "Сохранить изменения";
    }
  };

  const sendUpdateRequest = async (payload) => {
    const params = new URLSearchParams();
    params.set("auth_action", "update_profile");
    params.set("profile_name", payload.name);
    params.set("profile_description", payload.description);
    params.set("profile_avatar", payload.avatar);
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
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || !data.ok) {
      throw new Error((data && data.message) || "Не удалось сохранить изменения.");
    }
    return data;
  };

  let state = {
    name: safeName(defaultName),
    description: safeDescription(defaultBio),
    avatar: defaultAvatar,
  };
  render(state);

  avatarImageEl.addEventListener("error", () => {
    state.avatar = "";
    render(state);
    setMessage("Не удалось загрузить аватар, выберите другое изображение.", true);
  });

  avatarInputEl.addEventListener("change", () => {
    const file = avatarInputEl.files && avatarInputEl.files[0];
    if (!file) return;

    if (!file.type.startsWith("image/")) {
      setMessage("Выберите файл изображения.", true);
      avatarInputEl.value = "";
      return;
    }

    if (file.size > maxAvatarBytes) {
      setMessage("Изображение должно быть меньше 2 МБ.", true);
      avatarInputEl.value = "";
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      if (typeof reader.result !== "string") return;
      state.avatar = reader.result;
      render(state);
      setMessage("Фото обновлено. Нажмите «Сохранить изменения».");
    };
    reader.onerror = () => {
      setMessage("Не удалось прочитать файл.", true);
    };
    reader.readAsDataURL(file);
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    state = {
      ...state,
      name: safeName(nameInputEl.value).slice(0, 60),
      description: safeDescription(descriptionInputEl.value),
    };
    render(state);

    setBusy(true);
    sendUpdateRequest(state)
      .then((data) => {
        const user = (data && data.data && data.data.user) || null;
        if (user && typeof user === "object") {
          state = {
            ...state,
            name: safeName(typeof user.name === "string" ? user.name : state.name),
            description: safeDescription(
              typeof user.profile_bio === "string" ? user.profile_bio : state.description,
            ),
            avatar: typeof user.profile_avatar === "string" ? user.profile_avatar : state.avatar,
          };
          render(state);
        }
        setMessage((data && data.message) || "Изменения сохранены.");
      })
      .catch((error) => {
        setMessage(error instanceof Error ? error.message : "Не удалось сохранить изменения.", true);
      })
      .finally(() => {
        setBusy(false);
      });
  });
};

const initJournalSlider = () => {
  const slider = document.querySelector(".journal-card");
  if (!slider) return;

  const articles = Array.from(slider.querySelectorAll(".journal-article"));
  if (articles.length < 2) return;

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (prefersReducedMotion) return;
  const autoplayDelay = 4000;

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

  let timer = window.setInterval(advance, autoplayDelay);

  slider.addEventListener("mouseenter", () => {
    window.clearInterval(timer);
  });

  slider.addEventListener("mouseleave", () => {
    timer = window.setInterval(advance, autoplayDelay);
  });
};

const initHotToursSlider = () => {
  const section = document.querySelector(".section--hot-tours");
  if (!section) return;

  const header = section.querySelector(".hot-tours-header");
  const grid = section.querySelector(".hot-tours-grid");
  const track = section.querySelector(".hot-tours-track");
  const prevBtn = section.querySelector(".hot-tours-prev");
  const nextBtn = section.querySelector(".hot-tours-next");
  const moreBtn = section.querySelector(".hot-tours-more-btn");
  const footer = section.querySelector(".hot-tours-footer");
  const actions = section.querySelector(".hot-tours-actions");
  if (!header || !grid || !track || !prevBtn || !nextBtn) return;

  let progress = section.querySelector(".hot-tours-progress");
  if (!progress) {
    progress = document.createElement("div");
    progress.className = "hot-tours-progress";
    progress.innerHTML = '<div class="hot-tours-progress-track"><span class="hot-tours-progress-fill"></span></div>';
    if (footer && footer.parentNode) {
      footer.parentNode.insertBefore(progress, footer);
    } else if (grid.parentNode) {
      grid.parentNode.appendChild(progress);
    }
  }
  progress.hidden = true;
  const progressFill = progress ? progress.querySelector(".hot-tours-progress-fill") : null;

  const cards = Array.from(track.querySelectorAll(".hot-tour-card"));
  if (cards.length === 0) return;

  const isMobileLayout = () => window.matchMedia("(max-width: 768px)").matches;
  const getVisibleCount = () => (isMobileLayout() ? 1 : 5);
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const actionsInitialParent = actions && actions.parentNode ? actions.parentNode : null;
  const actionsInitialNextSibling = actions ? actions.nextSibling : null;
  let actionsInOverlay = false;

  const syncActionsPlacement = (isMobile) => {
    if (!actions || !actionsInitialParent) return;
    if (isMobile && !actionsInOverlay) {
      grid.appendChild(actions);
      actions.classList.add("is-overlay");
      actionsInOverlay = true;
      return;
    }
    if (!isMobile && actionsInOverlay) {
      if (actionsInitialNextSibling && actionsInitialNextSibling.parentNode === actionsInitialParent) {
        actionsInitialParent.insertBefore(actions, actionsInitialNextSibling);
      } else {
        actionsInitialParent.appendChild(actions);
      }
      actions.classList.remove("is-overlay");
      actionsInOverlay = false;
    }
  };

  let startIndex = 0;
  let loopVirtualIndex = 0;
  let loopActive = false;
  let loopSyncTimer = 0;
  let isExpanded = false;
  const loopBuffer = 2;

  const getRenderedCards = () => Array.from(track.querySelectorAll(".hot-tour-card"));

  const cloneLoopCard = (node) => {
    const clone = node.cloneNode(true);
    clone.setAttribute("data-loop-clone", "1");
    clone.setAttribute("aria-hidden", "true");
    if (clone.id) clone.removeAttribute("id");
    return clone;
  };

  const enableLoopMode = () => {
    if (loopActive || cards.length < 2) return;
    const prependCount = Math.min(loopBuffer, cards.length);
    const appendCount = Math.min(loopBuffer, cards.length);

    const prependFragment = document.createDocumentFragment();
    for (let i = cards.length - prependCount; i < cards.length; i += 1) {
      prependFragment.appendChild(cloneLoopCard(cards[i]));
    }
    track.insertBefore(prependFragment, track.firstChild);

    const appendFragment = document.createDocumentFragment();
    for (let i = 0; i < appendCount; i += 1) {
      appendFragment.appendChild(cloneLoopCard(cards[i]));
    }
    track.appendChild(appendFragment);

    loopActive = true;
    loopVirtualIndex = startIndex;
  };

  const disableLoopMode = () => {
    if (!loopActive) return;
    if (loopSyncTimer) {
      window.clearTimeout(loopSyncTimer);
      loopSyncTimer = 0;
    }
    track.querySelectorAll('[data-loop-clone="1"]').forEach((node) => node.remove());
    const count = cards.length || 1;
    startIndex = ((loopVirtualIndex % count) + count) % count;
    loopVirtualIndex = startIndex;
    loopActive = false;
  };

  const syncLoopBoundary = (immediate = false) => {
    if (!loopActive || isExpanded) return;
    const loopCount = cards.length;
    let nextLoopIndex = null;
    if (loopVirtualIndex >= loopCount) nextLoopIndex = 0;
    else if (loopVirtualIndex < 0) nextLoopIndex = loopCount - 1;
    if (nextLoopIndex === null) return;

    loopVirtualIndex = nextLoopIndex;
    const apply = () => update({ forceNoTransition: true });
    if (immediate) apply();
    else window.requestAnimationFrame(() => window.requestAnimationFrame(apply));
  };

  const parseTimeToMs = (rawValue) => {
    const value = String(rawValue || "").trim();
    if (!value) return 0;
    if (value.endsWith("ms")) return Number.parseFloat(value) || 0;
    if (value.endsWith("s")) return (Number.parseFloat(value) || 0) * 1000;
    return Number.parseFloat(value) || 0;
  };

  const getLoopFallbackDelay = () => {
    const styles = window.getComputedStyle(track);
    const durations = String(styles.transitionDuration || "").split(",");
    const delays = String(styles.transitionDelay || "").split(",");
    const count = Math.max(durations.length, delays.length);
    let maxTotal = 0;
    for (let i = 0; i < count; i += 1) {
      const duration = parseTimeToMs(durations[i] || durations[durations.length - 1] || "0ms");
      const delay = parseTimeToMs(delays[i] || delays[delays.length - 1] || "0ms");
      maxTotal = Math.max(maxTotal, duration + delay);
    }
    return Math.max(420, maxTotal) + 220;
  };

  const syncCardInteractivity = (activeIndex, centerOnly) => {
    const rendered = loopActive ? getRenderedCards() : cards;
    rendered.forEach((card, idx) => {
      const isCurrent = centerOnly && idx === activeIndex;
      card.classList.toggle("is-current", isCurrent);
      card.classList.toggle("is-side", centerOnly && !isCurrent);
      if (!centerOnly) {
        card.classList.remove("is-current", "is-side");
      }
    });
  };

  const getMaxCardHeight = () => {
    let maxHeight = 0;
    cards.forEach((card) => {
      maxHeight = Math.max(maxHeight, Math.ceil(card.getBoundingClientRect().height));
    });
    return maxHeight;
  };

  const goPrev = () => {
    if (isExpanded) return;
    if (loopActive) {
      loopVirtualIndex -= 1;
      update();
      return;
    }
    startIndex = Math.max(0, startIndex - 1);
    update();
  };

  const goNext = () => {
    if (isExpanded) return;
    if (loopActive) {
      loopVirtualIndex += 1;
      update();
      return;
    }
    const visibleCount = Math.max(1, getVisibleCount());
    const maxStart = Math.max(0, cards.length - visibleCount);
    startIndex = Math.min(maxStart, startIndex + 1);
    update();
  };

  const update = ({ forceNoTransition = false } = {}) => {
    const visibleCount = Math.max(1, getVisibleCount());
    const isMobile = isMobileLayout();
    const hasOverflow = cards.length > visibleCount;
    const shouldLoop = isMobile && hasOverflow && !isExpanded;
    if (shouldLoop) enableLoopMode();
    else disableLoopMode();
    syncActionsPlacement(isMobile);
    section.classList.toggle("is-carousel", hasOverflow);

    if (!hasOverflow) isExpanded = false;
    section.classList.toggle("is-expanded", hasOverflow && isExpanded);

    if (footer) footer.hidden = !hasOverflow;
    if (moreBtn) moreBtn.hidden = !hasOverflow || isExpanded;
    if (actions) actions.hidden = !hasOverflow || isExpanded;
    if (progress) progress.hidden = !isMobile || !hasOverflow || isExpanded || (!!actions && !actions.hidden);

    if (!hasOverflow) {
      startIndex = 0;
      loopVirtualIndex = 0;
      track.style.transform = "";
      track.style.transition = "";
      grid.style.height = "";
      syncCardInteractivity(0, false);
      if (progressFill) progressFill.style.width = "100%";
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      prevBtn.classList.add("is-disabled");
      nextBtn.classList.add("is-disabled");
      return;
    }

    const renderedCards = loopActive ? getRenderedCards() : cards;
    if (!renderedCards.length) return;

    if (isExpanded) {
      track.style.transform = "translateX(0px)";
      track.style.transition = forceNoTransition || prefersReducedMotion ? "none" : "transform 420ms ease";
      grid.style.height = `${Math.ceil(track.scrollHeight)}px`;
      syncCardInteractivity(0, false);
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      prevBtn.classList.add("is-disabled");
      nextBtn.classList.add("is-disabled");
      return;
    }

    const maxStart = Math.max(0, cards.length - visibleCount);
    let logicalIndex = startIndex;
    let physicalIndex = startIndex;

    if (loopActive) {
      const loopCount = cards.length;
      logicalIndex = ((loopVirtualIndex % loopCount) + loopCount) % loopCount;
      physicalIndex = loopVirtualIndex + Math.min(loopBuffer, loopCount);
      startIndex = logicalIndex;
    } else {
      startIndex = Math.min(startIndex, maxStart);
      logicalIndex = startIndex;
      physicalIndex = startIndex;
    }

    const safePhysicalIndex = Math.max(0, Math.min(renderedCards.length - 1, physicalIndex));
    const cardNode = renderedCards[safePhysicalIndex] || renderedCards[0];
    const cardRect = cardNode.getBoundingClientRect();
    const cardWidth = cardRect.width;
    const currentHeight = Math.ceil(cardRect.height);
    const uniformHeight = isMobile && hasOverflow && !isExpanded ? getMaxCardHeight() : 0;
    const cardHeight = Math.max(currentHeight, uniformHeight);
    const gridWidth = grid.getBoundingClientRect().width;
    const styles = window.getComputedStyle(track);
    const gap = parseFloat(styles.columnGap || styles.gap || "0");
    const baseOffset = (cardWidth + gap) * physicalIndex;
    const sidePeekOffset = isMobile ? Math.max(0, (gridWidth - cardWidth) / 2) : 0;
    const offset = baseOffset - sidePeekOffset;
    track.style.transform = `translateX(-${offset}px)`;
    track.style.transition = forceNoTransition || prefersReducedMotion ? "none" : "transform 420ms ease";
    grid.style.height = `${cardHeight}px`;
    const centerOnly = isMobile && hasOverflow && !isExpanded;
    syncCardInteractivity(safePhysicalIndex, centerOnly);
    if (progressFill) {
      const ratio = maxStart > 0 ? (logicalIndex + visibleCount) / cards.length : 1;
      progressFill.style.width = `${Math.max(0, Math.min(1, ratio)) * 100}%`;
    }

    if (loopActive) {
      prevBtn.disabled = false;
      nextBtn.disabled = false;
      prevBtn.classList.remove("is-disabled");
      nextBtn.classList.remove("is-disabled");
    } else {
      prevBtn.disabled = startIndex <= 0;
      nextBtn.disabled = startIndex >= maxStart;
      prevBtn.classList.toggle("is-disabled", prevBtn.disabled);
      nextBtn.classList.toggle("is-disabled", nextBtn.disabled);
    }

    if (loopActive) {
      if (loopSyncTimer) {
        window.clearTimeout(loopSyncTimer);
        loopSyncTimer = 0;
      }
      if (prefersReducedMotion || forceNoTransition) {
        syncLoopBoundary(false);
      } else if (loopVirtualIndex >= cards.length || loopVirtualIndex < 0) {
        loopSyncTimer = window.setTimeout(() => {
          loopSyncTimer = 0;
          syncLoopBoundary(false);
        }, getLoopFallbackDelay());
      }
    }
  };

  prevBtn.addEventListener("click", goPrev);

  nextBtn.addEventListener("click", goNext);

  if (moreBtn) {
    moreBtn.addEventListener("click", () => {
      if (isExpanded) return;
      isExpanded = true;
      update();
    });
  }

  track.addEventListener("transitionend", (event) => {
    if (event.propertyName !== "transform") return;
    if (event.target !== track) return;
    if (!loopActive || isExpanded) return;
    if (loopSyncTimer) {
      window.clearTimeout(loopSyncTimer);
      loopSyncTimer = 0;
    }
    syncLoopBoundary(false);
  });

  let touchStartX = 0;
  let touchStartY = 0;

  grid.addEventListener(
    "touchstart",
    (event) => {
      if (!isMobileLayout() || isExpanded) return;
      const touch = event.changedTouches && event.changedTouches[0];
      if (!touch) return;
      touchStartX = touch.clientX;
      touchStartY = touch.clientY;
    },
    { passive: true }
  );

  grid.addEventListener(
    "touchend",
    (event) => {
      if (!isMobileLayout() || isExpanded) return;
      const touch = event.changedTouches && event.changedTouches[0];
      if (!touch) return;

      const deltaX = touch.clientX - touchStartX;
      const deltaY = touch.clientY - touchStartY;
      if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) return;

      if (deltaX < 0) goNext();
      else goPrev();
    },
    { passive: true }
  );

  let dragStartX = 0;
  let dragStartY = 0;
  let isDragActive = false;

  grid.addEventListener("mousedown", (event) => {
    if (!isMobileLayout() || isExpanded || event.button !== 0) return;
    isDragActive = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
  });

  grid.addEventListener("mouseup", (event) => {
    if (!isDragActive || !isMobileLayout() || isExpanded) return;
    isDragActive = false;

    const deltaX = event.clientX - dragStartX;
    const deltaY = event.clientY - dragStartY;
    if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) return;

    if (deltaX < 0) goNext();
    else goPrev();
  });

  grid.addEventListener("mouseleave", () => {
    isDragActive = false;
  });

  window.addEventListener("resize", update);
  window.addEventListener("load", update);
  update();
};

const initDagestanSlider = () => {
  const section = document.querySelector(".section--places");
  if (!section) return;

  const banner = section.querySelector(".places-banner");
  const header = section.querySelector(".places-banner-header");
  const grid = section.querySelector(".places-grid");
  const track = section.querySelector(".places-track");
  const prevBtn = section.querySelector(".places-prev");
  const nextBtn = section.querySelector(".places-next");
  const moreBtn = section.querySelector(".places-more-btn");
  const footer = section.querySelector(".places-footer");
  const actions = section.querySelector(".places-banner-actions");
  if (!banner || !header || !grid || !track || !prevBtn || !nextBtn) return;

  let progress = section.querySelector(".places-progress");
  if (!progress) {
    progress = document.createElement("div");
    progress.className = "places-progress";
    progress.innerHTML = '<div class="places-progress-track"><span class="places-progress-fill"></span></div>';
    if (footer && footer.parentNode) {
      footer.parentNode.insertBefore(progress, footer);
    } else if (grid.parentNode) {
      grid.parentNode.appendChild(progress);
    }
  }
  progress.hidden = true;
  const progressFill = progress ? progress.querySelector(".places-progress-fill") : null;

  const cards = Array.from(track.querySelectorAll(".place-card"));
  if (cards.length === 0) return;

  const isMobileLayout = () => window.matchMedia("(max-width: 768px)").matches;
  const getVisibleCount = () => (isMobileLayout() ? 1 : 5);
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const actionsInitialParent = actions && actions.parentNode ? actions.parentNode : null;
  const actionsInitialNextSibling = actions ? actions.nextSibling : null;
  let actionsInOverlay = false;

  const syncActionsPlacement = (isMobile) => {
    if (!actions || !actionsInitialParent) return;
    if (isMobile && !actionsInOverlay) {
      grid.appendChild(actions);
      actions.classList.add("is-overlay");
      actionsInOverlay = true;
      return;
    }
    if (!isMobile && actionsInOverlay) {
      if (actionsInitialNextSibling && actionsInitialNextSibling.parentNode === actionsInitialParent) {
        actionsInitialParent.insertBefore(actions, actionsInitialNextSibling);
      } else {
        actionsInitialParent.appendChild(actions);
      }
      actions.classList.remove("is-overlay");
      actionsInOverlay = false;
    }
  };

  let startIndex = 0;
  let loopVirtualIndex = 0;
  let loopActive = false;
  let loopSyncTimer = 0;
  let isExpanded = false;
  const loopBuffer = 2;

  const getRenderedCards = () => Array.from(track.querySelectorAll(".place-card"));

  const cloneLoopCard = (node) => {
    const clone = node.cloneNode(true);
    clone.setAttribute("data-loop-clone", "1");
    clone.setAttribute("aria-hidden", "true");
    if (clone.id) clone.removeAttribute("id");
    return clone;
  };

  const enableLoopMode = () => {
    if (loopActive || cards.length < 2) return;
    const prependCount = Math.min(loopBuffer, cards.length);
    const appendCount = Math.min(loopBuffer, cards.length);

    const prependFragment = document.createDocumentFragment();
    for (let i = cards.length - prependCount; i < cards.length; i += 1) {
      prependFragment.appendChild(cloneLoopCard(cards[i]));
    }
    track.insertBefore(prependFragment, track.firstChild);

    const appendFragment = document.createDocumentFragment();
    for (let i = 0; i < appendCount; i += 1) {
      appendFragment.appendChild(cloneLoopCard(cards[i]));
    }
    track.appendChild(appendFragment);

    loopActive = true;
    loopVirtualIndex = startIndex;
  };

  const disableLoopMode = () => {
    if (!loopActive) return;
    if (loopSyncTimer) {
      window.clearTimeout(loopSyncTimer);
      loopSyncTimer = 0;
    }
    track.querySelectorAll('[data-loop-clone="1"]').forEach((node) => node.remove());
    const count = cards.length || 1;
    startIndex = ((loopVirtualIndex % count) + count) % count;
    loopVirtualIndex = startIndex;
    loopActive = false;
  };

  const syncLoopBoundary = (immediate = false) => {
    if (!loopActive || isExpanded) return;
    const loopCount = cards.length;
    let nextLoopIndex = null;
    if (loopVirtualIndex >= loopCount) nextLoopIndex = 0;
    else if (loopVirtualIndex < 0) nextLoopIndex = loopCount - 1;
    if (nextLoopIndex === null) return;

    loopVirtualIndex = nextLoopIndex;
    const apply = () => update({ forceNoTransition: true });
    if (immediate) apply();
    else window.requestAnimationFrame(() => window.requestAnimationFrame(apply));
  };

  const parseTimeToMs = (rawValue) => {
    const value = String(rawValue || "").trim();
    if (!value) return 0;
    if (value.endsWith("ms")) return Number.parseFloat(value) || 0;
    if (value.endsWith("s")) return (Number.parseFloat(value) || 0) * 1000;
    return Number.parseFloat(value) || 0;
  };

  const getLoopFallbackDelay = () => {
    const styles = window.getComputedStyle(track);
    const durations = String(styles.transitionDuration || "").split(",");
    const delays = String(styles.transitionDelay || "").split(",");
    const count = Math.max(durations.length, delays.length);
    let maxTotal = 0;
    for (let i = 0; i < count; i += 1) {
      const duration = parseTimeToMs(durations[i] || durations[durations.length - 1] || "0ms");
      const delay = parseTimeToMs(delays[i] || delays[delays.length - 1] || "0ms");
      maxTotal = Math.max(maxTotal, duration + delay);
    }
    return Math.max(420, maxTotal) + 220;
  };

  const syncCardInteractivity = (activeIndex, centerOnly) => {
    const rendered = loopActive ? getRenderedCards() : cards;
    rendered.forEach((card, idx) => {
      const isCurrent = centerOnly && idx === activeIndex;
      card.classList.toggle("is-current", isCurrent);
      card.classList.toggle("is-side", centerOnly && !isCurrent);
      if (!centerOnly) {
        card.classList.remove("is-current", "is-side");
      }
    });
  };

  const getMaxCardHeight = () => {
    let maxHeight = 0;
    cards.forEach((card) => {
      maxHeight = Math.max(maxHeight, Math.ceil(card.getBoundingClientRect().height));
    });
    return maxHeight;
  };

  const goPrev = () => {
    if (isExpanded) return;
    if (loopActive) {
      loopVirtualIndex -= 1;
      update();
      return;
    }
    startIndex = Math.max(0, startIndex - 1);
    update();
  };

  const goNext = () => {
    if (isExpanded) return;
    if (loopActive) {
      loopVirtualIndex += 1;
      update();
      return;
    }
    const visibleCount = Math.max(1, getVisibleCount());
    const maxStart = Math.max(0, cards.length - visibleCount);
    startIndex = Math.min(maxStart, startIndex + 1);
    update();
  };

  const update = ({ forceNoTransition = false } = {}) => {
    const visibleCount = Math.max(1, getVisibleCount());
    const isMobile = isMobileLayout();
    const hasOverflow = cards.length > visibleCount;
    const shouldLoop = isMobile && hasOverflow && !isExpanded;
    if (shouldLoop) enableLoopMode();
    else disableLoopMode();
    syncActionsPlacement(isMobile);
    banner.classList.toggle("places-banner--slider", hasOverflow);

    if (!hasOverflow) isExpanded = false;
    banner.classList.toggle("is-expanded", hasOverflow && isExpanded);

    if (footer) footer.hidden = !hasOverflow;
    if (moreBtn) moreBtn.hidden = !hasOverflow || isExpanded;
    if (actions) actions.hidden = !hasOverflow || isExpanded;
    if (progress) progress.hidden = !isMobile || !hasOverflow || isExpanded || (!!actions && !actions.hidden);

    if (!hasOverflow) {
      startIndex = 0;
      loopVirtualIndex = 0;
      track.style.transform = "";
      track.style.transition = "";
      grid.style.height = "";
      syncCardInteractivity(0, false);
      if (progressFill) progressFill.style.width = "100%";
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      prevBtn.classList.add("is-disabled");
      nextBtn.classList.add("is-disabled");
      return;
    }

    const renderedCards = loopActive ? getRenderedCards() : cards;
    if (!renderedCards.length) return;

    if (isExpanded) {
      track.style.transform = "translateX(0px)";
      track.style.transition = forceNoTransition || prefersReducedMotion ? "none" : "transform 420ms ease";
      grid.style.height = `${Math.ceil(track.scrollHeight)}px`;
      syncCardInteractivity(0, false);
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      prevBtn.classList.add("is-disabled");
      nextBtn.classList.add("is-disabled");
      return;
    }

    const maxStart = Math.max(0, cards.length - visibleCount);
    let logicalIndex = startIndex;
    let physicalIndex = startIndex;

    if (loopActive) {
      const loopCount = cards.length;
      logicalIndex = ((loopVirtualIndex % loopCount) + loopCount) % loopCount;
      physicalIndex = loopVirtualIndex + Math.min(loopBuffer, loopCount);
      startIndex = logicalIndex;
    } else {
      startIndex = Math.min(startIndex, maxStart);
      logicalIndex = startIndex;
      physicalIndex = startIndex;
    }

    const safePhysicalIndex = Math.max(0, Math.min(renderedCards.length - 1, physicalIndex));
    const cardNode = renderedCards[safePhysicalIndex] || renderedCards[0];
    const cardRect = cardNode.getBoundingClientRect();
    const cardWidth = cardRect.width;
    const currentHeight = Math.ceil(cardRect.height);
    const uniformHeight = isMobile && hasOverflow && !isExpanded ? getMaxCardHeight() : 0;
    const cardHeight = Math.max(currentHeight, uniformHeight);
    const gridWidth = grid.getBoundingClientRect().width;
    const styles = window.getComputedStyle(track);
    const gap = parseFloat(styles.columnGap || styles.gap || "0");
    const baseOffset = (cardWidth + gap) * physicalIndex;
    const sidePeekOffset = isMobile ? Math.max(0, (gridWidth - cardWidth) / 2) : 0;
    const offset = baseOffset - sidePeekOffset;
    track.style.transform = `translateX(-${offset}px)`;
    track.style.transition = forceNoTransition || prefersReducedMotion ? "none" : "transform 420ms ease";
    grid.style.height = `${cardHeight}px`;
    const centerOnly = isMobile && hasOverflow && !isExpanded;
    syncCardInteractivity(safePhysicalIndex, centerOnly);
    if (progressFill) {
      const ratio = maxStart > 0 ? (logicalIndex + visibleCount) / cards.length : 1;
      progressFill.style.width = `${Math.max(0, Math.min(1, ratio)) * 100}%`;
    }

    if (loopActive) {
      prevBtn.disabled = false;
      nextBtn.disabled = false;
      prevBtn.classList.remove("is-disabled");
      nextBtn.classList.remove("is-disabled");
    } else {
      prevBtn.disabled = startIndex <= 0;
      nextBtn.disabled = startIndex >= maxStart;
      prevBtn.classList.toggle("is-disabled", prevBtn.disabled);
      nextBtn.classList.toggle("is-disabled", nextBtn.disabled);
    }

    if (loopActive) {
      if (loopSyncTimer) {
        window.clearTimeout(loopSyncTimer);
        loopSyncTimer = 0;
      }
      if (prefersReducedMotion || forceNoTransition) {
        syncLoopBoundary(false);
      } else if (loopVirtualIndex >= cards.length || loopVirtualIndex < 0) {
        loopSyncTimer = window.setTimeout(() => {
          loopSyncTimer = 0;
          syncLoopBoundary(false);
        }, getLoopFallbackDelay());
      }
    }
  };

  prevBtn.addEventListener("click", goPrev);

  nextBtn.addEventListener("click", goNext);

  if (moreBtn) {
    moreBtn.addEventListener("click", () => {
      if (isExpanded) return;
      isExpanded = true;
      update();
    });
  }

  track.addEventListener("transitionend", (event) => {
    if (event.propertyName !== "transform") return;
    if (event.target !== track) return;
    if (!loopActive || isExpanded) return;
    if (loopSyncTimer) {
      window.clearTimeout(loopSyncTimer);
      loopSyncTimer = 0;
    }
    syncLoopBoundary(false);
  });

  let touchStartX = 0;
  let touchStartY = 0;

  grid.addEventListener(
    "touchstart",
    (event) => {
      if (!isMobileLayout() || isExpanded) return;
      const touch = event.changedTouches && event.changedTouches[0];
      if (!touch) return;
      touchStartX = touch.clientX;
      touchStartY = touch.clientY;
    },
    { passive: true }
  );

  grid.addEventListener(
    "touchend",
    (event) => {
      if (!isMobileLayout() || isExpanded) return;
      const touch = event.changedTouches && event.changedTouches[0];
      if (!touch) return;

      const deltaX = touch.clientX - touchStartX;
      const deltaY = touch.clientY - touchStartY;
      if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) return;

      if (deltaX < 0) goNext();
      else goPrev();
    },
    { passive: true }
  );

  let dragStartX = 0;
  let dragStartY = 0;
  let isDragActive = false;

  grid.addEventListener("mousedown", (event) => {
    if (!isMobileLayout() || isExpanded || event.button !== 0) return;
    isDragActive = true;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
  });

  grid.addEventListener("mouseup", (event) => {
    if (!isDragActive || !isMobileLayout() || isExpanded) return;
    isDragActive = false;

    const deltaX = event.clientX - dragStartX;
    const deltaY = event.clientY - dragStartY;
    if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) return;

    if (deltaX < 0) goNext();
    else goPrev();
  });

  grid.addEventListener("mouseleave", () => {
    isDragActive = false;
  });

  window.addEventListener("resize", update);
  window.addEventListener("load", update);
  update();
};

const initRegionActualSlider = () => {
  const sections = Array.from(document.querySelectorAll(".section--actual[data-actual-slider]"));
  if (!sections.length) return;

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  sections.forEach((section) => {
    const grid = section.querySelector(".actual-grid");
    const track = section.querySelector(".actual-track");
    const progress = section.querySelector("[data-actual-progress]");
    const progressTrack = section.querySelector("[data-actual-progress-track]");
    const progressFill = section.querySelector("[data-actual-progress-fill]");
    if (!grid || !track) return;

    const cards = Array.from(track.querySelectorAll(".actual-card"));
    if (!cards.length) {
      if (progress) progress.hidden = true;
      return;
    }

    const isMobileLayout = () => window.matchMedia("(max-width: 768px)").matches;
    const getVisibleCount = () => (isMobileLayout() ? 1 : 2);
    let startIndex = 0;
    let maxStart = 0;

    const goPrev = () => {
      if (maxStart <= 0) return;
      startIndex = Math.max(0, startIndex - 1);
      update();
    };

    const goNext = () => {
      if (maxStart <= 0) return;
      startIndex = Math.min(maxStart, startIndex + 1);
      update();
    };

    const syncProgress = (hasOverflow, visibleCount) => {
      if (!progress || !progressTrack || !progressFill) return;
      progress.hidden = !hasOverflow;

      if (!hasOverflow) {
        progressTrack.setAttribute("aria-valuemax", "0");
        progressTrack.setAttribute("aria-valuenow", "0");
        progressTrack.tabIndex = -1;
        progressFill.style.width = "100%";
        return;
      }

      const now = Math.max(0, Math.min(maxStart, startIndex));
      const range = maxStart || 1;
      const progressByPosition = now / range;
      const minViewportFill = Math.max(visibleCount / cards.length, 0);
      const fill = Math.max(minViewportFill, progressByPosition);
      progressTrack.setAttribute("aria-valuemax", String(maxStart));
      progressTrack.setAttribute("aria-valuenow", String(now));
      progressTrack.tabIndex = 0;
      progressFill.style.width = `${Math.min(100, Math.max(8, fill * 100))}%`;
    };

    const update = () => {
      const visibleCount = Math.max(1, getVisibleCount());
      const hasOverflow = cards.length > visibleCount;
      maxStart = Math.max(0, cards.length - visibleCount);

      if (!hasOverflow) {
        startIndex = 0;
        track.style.transform = "translateX(0px)";
        track.style.transition = "";
        grid.style.height = cards.length ? `${Math.ceil(cards[0].getBoundingClientRect().height)}px` : "";
        syncProgress(false, visibleCount);
        return;
      }

      startIndex = Math.min(startIndex, maxStart);
      const cardWidth = cards[0].getBoundingClientRect().width;
      const styles = window.getComputedStyle(track);
      const gap = parseFloat(styles.columnGap || styles.gap || "0");
      const offset = (cardWidth + gap) * startIndex;

      track.style.transform = `translateX(-${offset}px)`;
      track.style.transition = prefersReducedMotion ? "none" : "transform 420ms ease";
      grid.style.height = `${Math.ceil(cards[0].getBoundingClientRect().height)}px`;
      syncProgress(true, visibleCount);
    };

    const setIndexByPointer = (clientX) => {
      if (!progressTrack || maxStart <= 0) return;
      const rect = progressTrack.getBoundingClientRect();
      if (rect.width <= 0) return;
      const ratio = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
      startIndex = Math.round(ratio * maxStart);
      update();
    };

    if (progressTrack) {
      let draggingPointerId = null;

      progressTrack.addEventListener("pointerdown", (event) => {
        if (maxStart <= 0) return;
        draggingPointerId = event.pointerId;
        progressTrack.setPointerCapture(event.pointerId);
        setIndexByPointer(event.clientX);
      });

      progressTrack.addEventListener("pointermove", (event) => {
        if (draggingPointerId !== event.pointerId) return;
        setIndexByPointer(event.clientX);
      });

      const stopDragging = (event) => {
        if (draggingPointerId !== event.pointerId) return;
        draggingPointerId = null;
        if (progressTrack.hasPointerCapture(event.pointerId)) {
          progressTrack.releasePointerCapture(event.pointerId);
        }
      };

      progressTrack.addEventListener("pointerup", stopDragging);
      progressTrack.addEventListener("pointercancel", stopDragging);

      progressTrack.addEventListener("keydown", (event) => {
        if (maxStart <= 0) return;
        if (event.key === "ArrowLeft") {
          event.preventDefault();
          goPrev();
        } else if (event.key === "ArrowRight") {
          event.preventDefault();
          goNext();
        } else if (event.key === "Home") {
          event.preventDefault();
          startIndex = 0;
          update();
        } else if (event.key === "End") {
          event.preventDefault();
          startIndex = maxStart;
          update();
        }
      });
    }

    let touchStartX = 0;
    let touchStartY = 0;

    grid.addEventListener(
      "touchstart",
      (event) => {
        if (!isMobileLayout() || maxStart <= 0) return;
        const touch = event.changedTouches && event.changedTouches[0];
        if (!touch) return;
        touchStartX = touch.clientX;
        touchStartY = touch.clientY;
      },
      { passive: true }
    );

    grid.addEventListener(
      "touchend",
      (event) => {
        if (!isMobileLayout() || maxStart <= 0) return;
        const touch = event.changedTouches && event.changedTouches[0];
        if (!touch) return;
        const deltaX = touch.clientX - touchStartX;
        const deltaY = touch.clientY - touchStartY;
        if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) return;
        if (deltaX < 0) goNext();
        else goPrev();
      },
      { passive: true }
    );

    let dragStartX = 0;
    let dragStartY = 0;
    let isDragActive = false;

    grid.addEventListener("mousedown", (event) => {
      if (!isMobileLayout() || maxStart <= 0 || event.button !== 0) return;
      isDragActive = true;
      dragStartX = event.clientX;
      dragStartY = event.clientY;
    });

    grid.addEventListener("mouseup", (event) => {
      if (!isDragActive || !isMobileLayout() || maxStart <= 0) return;
      isDragActive = false;
      const deltaX = event.clientX - dragStartX;
      const deltaY = event.clientY - dragStartY;
      if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) return;
      if (deltaX < 0) goNext();
      else goPrev();
    });

    grid.addEventListener("mouseleave", () => {
      isDragActive = false;
    });

    window.addEventListener("resize", update);
    window.addEventListener("load", update);
    update();
  });
};

const initRegionMediaPreview = () => {
  const grid = document.querySelector("[data-region-media-grid]");
  const moreBtn = document.querySelector("[data-region-media-more]");
  if (!grid || !moreBtn) return;

  const cards = Array.from(grid.querySelectorAll(".region-media-card"));
  const limitRaw = Number(grid.dataset.regionMediaLimit || "8");
  const limit = Number.isFinite(limitRaw) && limitRaw > 0 ? Math.floor(limitRaw) : 8;
  if (cards.length <= limit) {
    moreBtn.hidden = true;
    grid.style.maxHeight = "none";
    return;
  }

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (prefersReducedMotion) grid.style.transition = "none";
  let visibleCount = Math.min(limit, cards.length);

  const getHeightForVisibleCount = (count) => {
    const safeCount = Math.max(1, Math.min(cards.length, count));
    const lastVisibleCard = cards[safeCount - 1];
    if (!(lastVisibleCard instanceof HTMLElement)) return 0;
    const gridRect = grid.getBoundingClientRect();
    const cardRect = lastVisibleCard.getBoundingClientRect();
    return Math.max(0, Math.ceil(cardRect.bottom - gridRect.top));
  };

  const applyVisibleState = () => {
    if (visibleCount >= cards.length) {
      grid.classList.add("is-expanded");
      grid.style.maxHeight = "none";
      moreBtn.hidden = true;
      moreBtn.setAttribute("aria-expanded", "true");
      return;
    }

    grid.classList.remove("is-expanded");
    const height = getHeightForVisibleCount(visibleCount);
    if (height > 0) grid.style.maxHeight = `${height}px`;
    moreBtn.hidden = false;
    moreBtn.setAttribute("aria-expanded", "false");
  };

  const measureSoon = () => {
    window.requestAnimationFrame(applyVisibleState);
  };

  const showNextBatch = () => {
    visibleCount = Math.min(cards.length, visibleCount + limit);
    applyVisibleState();
  };

  cards.forEach((card) => {
    const media = card.querySelector("img, video");
    if (!media) return;
    media.addEventListener("load", measureSoon);
    media.addEventListener("loadedmetadata", measureSoon);
  });

  applyVisibleState();
  moreBtn.addEventListener("click", showNextBatch);
  window.addEventListener("resize", measureSoon);
  window.addEventListener("load", measureSoon);
};

const initMediaLightbox = (gallerySelector, itemSelector, modalSelector) => {
  const gallery = document.querySelector(gallerySelector);
  const modal = document.querySelector(modalSelector);
  if (!gallery || !modal) return;

  const items = Array.from(gallery.querySelectorAll(itemSelector)).filter(
    (item) => !(item instanceof HTMLElement) || item.dataset.galleryType !== "video",
  );
  const imageEl = modal.querySelector("[data-gallery-image]");
  const videoEl = modal.querySelector("[data-gallery-video]");
  const counterEl = modal.querySelector("[data-gallery-counter]");
  const closeBtn = modal.querySelector('[data-gallery-close="button"]');
  const backdrop = modal.querySelector('[data-gallery-close="backdrop"]');
  const prevBtn = modal.querySelector('[data-gallery-nav="prev"]');
  const nextBtn = modal.querySelector('[data-gallery-nav="next"]');
  if (!items.length || !(imageEl instanceof HTMLImageElement) || !counterEl || !closeBtn || !backdrop || !prevBtn || !nextBtn) return;

  let activeIndex = 0;
  const hasVideo = videoEl instanceof HTMLVideoElement;
  if (hasVideo) {
    videoEl.pause();
    videoEl.removeAttribute("src");
    videoEl.load();
    videoEl.hidden = true;
  }

  const updateView = () => {
    const item = items[activeIndex];
    if (!(item instanceof HTMLElement)) return;
    const src = item.dataset.gallerySrc || "";
    const alt = item.dataset.galleryAlt || "";

    if (hasVideo) {
      videoEl.pause();
      videoEl.removeAttribute("src");
      videoEl.load();
      videoEl.hidden = true;
    }
    imageEl.hidden = false;
    imageEl.src = src;
    imageEl.alt = alt;

    counterEl.textContent = `${activeIndex + 1} / ${items.length}`;
  };

  const openModal = (index) => {
    activeIndex = Math.max(0, Math.min(items.length - 1, index));
    updateView();
    modal.hidden = false;
    window.requestAnimationFrame(() => {
      modal.classList.add("is-open");
      document.body.classList.add("hotel-lightbox-open");
    });
  };

  const closeModal = () => {
    modal.classList.remove("is-open");
    window.setTimeout(() => {
      if (!modal.classList.contains("is-open")) modal.hidden = true;
    }, 160);
    if (hasVideo) {
      videoEl.pause();
      videoEl.removeAttribute("src");
      videoEl.load();
      videoEl.hidden = true;
    }
    document.body.classList.remove("hotel-lightbox-open");
  };

  const showPrevious = () => {
    if (!items.length) return;
    activeIndex = (activeIndex - 1 + items.length) % items.length;
    updateView();
  };

  const showNext = () => {
    if (!items.length) return;
    activeIndex = (activeIndex + 1) % items.length;
    updateView();
  };

  items.forEach((item, index) => {
    item.addEventListener("click", () => openModal(index));
  });

  closeBtn.addEventListener("click", closeModal);
  backdrop.addEventListener("click", closeModal);
  prevBtn.addEventListener("click", showPrevious);
  nextBtn.addEventListener("click", showNext);

  document.addEventListener("keydown", (event) => {
    if (!modal.classList.contains("is-open")) return;
    if (event.key === "Escape") {
      closeModal();
      return;
    }
    if (event.key === "ArrowLeft") {
      showPrevious();
      return;
    }
    if (event.key === "ArrowRight") {
      showNext();
    }
  });
};

const initHotelHeroThumbLayout = () => {
  const galleries = Array.from(document.querySelectorAll("[data-hotel-gallery]"));
  if (!galleries.length) return;

  const MIN_THUMB_WIDTH = 96;

  const updateGallery = (gallery) => {
    const strip = gallery.querySelector(".hotel-hero-gallery-strip");
    const primary = gallery.querySelector(".hotel-media-item--primary");
    if (!(strip instanceof HTMLElement) || !(primary instanceof HTMLElement)) return;

    const thumbTiles = Array.from(strip.querySelectorAll(".hotel-media-item--thumb"));
    if (!thumbTiles.length) return;

    const styles = window.getComputedStyle(strip);
    const gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
    const mainWidth = primary.getBoundingClientRect().width;
    if (!mainWidth) return;

    const maxThumbs = Math.min(4, thumbTiles.length);

    const fits = (count) => {
      if (count <= 1) return true;
      const thumbWidth = (mainWidth - gap * (count - 1)) / count;
      return thumbWidth >= MIN_THUMB_WIDTH;
    };

    let visibleThumbs = maxThumbs;
    if (maxThumbs >= 4 && !fits(4)) {
      visibleThumbs = fits(3) ? 3 : 2;
    } else if (maxThumbs === 3 && !fits(3)) {
      visibleThumbs = 2;
    }

    visibleThumbs = Math.max(1, Math.min(maxThumbs, visibleThumbs));
    gallery.dataset.thumbCount = String(visibleThumbs);

    const totalItems = gallery.querySelectorAll("[data-hotel-gallery-item]").length;
    const totalThumbs = Math.max(0, totalItems - 1);
    const hiddenThumbs = Math.max(0, totalThumbs - visibleThumbs);

    thumbTiles.forEach((tile, index) => {
      const itemBtn = tile.querySelector("[data-hotel-gallery-item]");
      if (!(itemBtn instanceof HTMLElement)) return;

      if (!itemBtn.dataset.defaultAriaLabel) {
        itemBtn.dataset.defaultAriaLabel = itemBtn.getAttribute("aria-label") || "";
      }

      const existingMoreLabel = tile.querySelector(".hotel-hero-gallery-more");
      tile.classList.remove("is-more");
      if (existingMoreLabel instanceof HTMLElement) existingMoreLabel.hidden = true;
      if (index + 1 > visibleThumbs) return;

      if (hiddenThumbs > 0 && index + 1 === visibleThumbs) {
        tile.classList.add("is-more");
        let moreLabel = existingMoreLabel;
        if (!(moreLabel instanceof HTMLElement)) {
          moreLabel = document.createElement("span");
          moreLabel.className = "hotel-hero-gallery-more";
          const trigger = tile.querySelector(".hotel-media-trigger");
          if (trigger) trigger.appendChild(moreLabel);
        }
        if (moreLabel instanceof HTMLElement) {
          moreLabel.innerHTML = `+${hiddenThumbs}<small> фото</small>`;
          moreLabel.hidden = false;
        }
        itemBtn.setAttribute("aria-label", `Открыть галерею, ещё ${hiddenThumbs} фото`);
        return;
      }

      const fallbackIndex = Number.parseInt(itemBtn.dataset.galleryIndex || "", 10);
      const fallbackLabel = Number.isFinite(fallbackIndex)
        ? `Открыть фото ${fallbackIndex + 1}`
        : "Открыть фото";
      itemBtn.setAttribute("aria-label", itemBtn.dataset.defaultAriaLabel || fallbackLabel);
    });
  };

  let rafId = 0;
  const scheduleUpdate = () => {
    if (rafId) window.cancelAnimationFrame(rafId);
    rafId = window.requestAnimationFrame(() => {
      rafId = 0;
      galleries.forEach(updateGallery);
    });
  };

  galleries.forEach((gallery) => {
    const primaryImage = gallery.querySelector(".hotel-media-item--primary img");
    if (primaryImage instanceof HTMLImageElement) {
      primaryImage.addEventListener("load", scheduleUpdate);
    }
  });

  scheduleUpdate();
  window.addEventListener("resize", scheduleUpdate);
  window.addEventListener("load", scheduleUpdate);
};

const initHotelMediaGallery = () => {
  initHotelHeroThumbLayout();
  initMediaLightbox("[data-hotel-gallery]", "[data-hotel-gallery-item]", "[data-hotel-gallery-modal]");
};

const initHotelRoomGallery = () => {
  const modal = document.querySelector("[data-hotel-room-gallery-modal]");
  if (!(modal instanceof HTMLElement)) return;

  const roomItems = Array.from(document.querySelectorAll("[data-room-gallery-item]"));
  const roomOpenButtons = Array.from(document.querySelectorAll("[data-room-gallery-open]"));
  if (!roomItems.length || !roomOpenButtons.length) return;

  const imageEl = modal.querySelector("[data-room-gallery-image]");
  const counterEl = modal.querySelector("[data-room-gallery-counter]");
  const closeBtn = modal.querySelector('[data-room-gallery-close="button"]');
  const backdrop = modal.querySelector('[data-room-gallery-close="backdrop"]');
  const prevBtn = modal.querySelector('[data-room-gallery-nav="prev"]');
  const nextBtn = modal.querySelector('[data-room-gallery-nav="next"]');

  if (
    !(imageEl instanceof HTMLImageElement) ||
    !(counterEl instanceof HTMLElement) ||
    !(closeBtn instanceof HTMLButtonElement) ||
    !(backdrop instanceof HTMLElement) ||
    !(prevBtn instanceof HTMLButtonElement) ||
    !(nextBtn instanceof HTMLButtonElement)
  ) {
    return;
  }

  let activeGroupItems = [];
  let activeIndex = 0;

  const getGroupItems = (group) =>
    roomItems.filter(
      (item) => item instanceof HTMLElement && String(item.dataset.roomGalleryGroup || "") === String(group || ""),
    );

  const updateView = () => {
    const activeItem = activeGroupItems[activeIndex];
    if (!(activeItem instanceof HTMLElement)) return;
    imageEl.src = activeItem.dataset.gallerySrc || "";
    imageEl.alt = activeItem.dataset.galleryAlt || "";
    counterEl.textContent = `${activeIndex + 1} / ${activeGroupItems.length}`;
  };

  const openModal = (group, startIndex = 0) => {
    const groupItems = getGroupItems(group);
    if (!groupItems.length) return;
    activeGroupItems = groupItems;
    activeIndex = Math.max(0, Math.min(groupItems.length - 1, startIndex));
    updateView();
    modal.hidden = false;
    window.requestAnimationFrame(() => {
      modal.classList.add("is-open");
      document.body.classList.add("hotel-lightbox-open");
    });
  };

  const closeModal = () => {
    modal.classList.remove("is-open");
    window.setTimeout(() => {
      if (!modal.classList.contains("is-open")) modal.hidden = true;
    }, 160);
    imageEl.removeAttribute("src");
    imageEl.alt = "";
    document.body.classList.remove("hotel-lightbox-open");
  };

  const showPrevious = () => {
    if (!activeGroupItems.length) return;
    activeIndex = (activeIndex - 1 + activeGroupItems.length) % activeGroupItems.length;
    updateView();
  };

  const showNext = () => {
    if (!activeGroupItems.length) return;
    activeIndex = (activeIndex + 1) % activeGroupItems.length;
    updateView();
  };

  roomOpenButtons.forEach((button) => {
    if (!(button instanceof HTMLElement)) return;
    button.addEventListener("click", () => {
      const group = button.dataset.roomGalleryGroup || "";
      openModal(group, 0);
    });
  });

  roomItems.forEach((item) => {
    if (!(item instanceof HTMLElement)) return;
    item.addEventListener("click", () => {
      const group = item.dataset.roomGalleryGroup || "";
      const groupItems = getGroupItems(group);
      const index = groupItems.indexOf(item);
      openModal(group, index >= 0 ? index : 0);
    });
  });

  closeBtn.addEventListener("click", closeModal);
  backdrop.addEventListener("click", closeModal);
  prevBtn.addEventListener("click", showPrevious);
  nextBtn.addEventListener("click", showNext);

  document.addEventListener("keydown", (event) => {
    if (!modal.classList.contains("is-open")) return;
    if (event.key === "Escape") {
      closeModal();
      return;
    }
    if (event.key === "ArrowLeft") {
      showPrevious();
      return;
    }
    if (event.key === "ArrowRight") {
      showNext();
    }
  });
};

const initRegionMediaGallery = () => {
  initMediaLightbox("[data-region-gallery]", "[data-region-gallery-item]", "[data-region-gallery-modal]");
};

const initGuideReviewsGallery = () => {
  initMediaLightbox("[data-guide-reviews-gallery]", "[data-guide-review-photo]", "[data-guide-review-modal]");
};

const initTourReviewsGallery = () => {
  initMediaLightbox("[data-tour-reviews-gallery]", "[data-tour-review-photo]", "[data-tour-review-modal]");
};

const initHotelRoomOffersSlider = () => {
  const sliders = Array.from(document.querySelectorAll("[data-room-offers]"));
  if (sliders.length === 0) return;

  sliders.forEach((slider) => {
    const track = slider.querySelector("[data-room-offers-track]");
    const prevBtn = slider.querySelector("[data-room-offers-nav='prev']");
    const nextBtn = slider.querySelector("[data-room-offers-nav='next']");
    if (
      !(track instanceof HTMLElement) ||
      !(prevBtn instanceof HTMLButtonElement) ||
      !(nextBtn instanceof HTMLButtonElement)
    ) {
      return;
    }

    const getVisibleCards = () =>
      Array.from(track.querySelectorAll(".hotel-offer-card")).filter(
        (card) => card instanceof HTMLElement && !card.hidden,
      );

    const step = () => {
      const firstCard = getVisibleCards()[0];
      if (!(firstCard instanceof HTMLElement)) return 260;
      const cardWidth = firstCard.getBoundingClientRect().width;
      const styles = window.getComputedStyle(track);
      const gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
      return cardWidth + gap;
    };

    const updateButtons = () => {
      const visibleCards = getVisibleCards();
      if (!visibleCards.length) {
        prevBtn.disabled = true;
        nextBtn.disabled = true;
        slider.classList.remove("is-scrollable", "has-prev", "has-next");
        return;
      }

      const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
      const left = track.scrollLeft;
      const canPrev = left > 2;
      const canNext = left < maxScroll - 2;
      prevBtn.disabled = !canPrev;
      nextBtn.disabled = !canNext;
      slider.classList.toggle("is-scrollable", visibleCards.length > 1 && maxScroll > 2);
      slider.classList.toggle("has-prev", canPrev);
      slider.classList.toggle("has-next", canNext);
    };

    prevBtn.addEventListener("click", () => {
      track.scrollBy({ left: -step(), behavior: "smooth" });
    });

    nextBtn.addEventListener("click", () => {
      track.scrollBy({ left: step(), behavior: "smooth" });
    });

    track.addEventListener("scroll", updateButtons, { passive: true });
    window.addEventListener("resize", updateButtons);
    window.addEventListener("load", updateButtons);
    updateButtons();
  });
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
  initHotelRoomsSearchControls();
  initDateRangeFields();
  initHeroCustomSelects();
  initWhereFieldAutoGrow();
  initAuthModal();
  initContactsModal();
  initHomeMobileMenu();
  initAuthLogout();
  initProfileEditor();
  initJournalSlider();
  initDagestanSlider();
  initHotToursSlider();
  initRegionActualSlider();
  initHotelMediaGallery();
  initHotelRoomGallery();
  initHotelRoomOffersSlider();
  initRegionMediaPreview();
  initRegionMediaGallery();
  initGuideReviewsGallery();
  initTourReviewsGallery();
  initTourDaysAccordion();
});
