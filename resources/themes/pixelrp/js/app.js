/*
 * PixelRP theme behaviour. Everything the pages need and nothing more:
 * no framework, one small module per interaction, all progressive - every
 * page works with JavaScript off, this only makes it nicer.
 */

const THEME_KEY = "prp-theme";
const SCENE_KEY = "prp-scene-paused";

const read = (key) => {
    try {
        return localStorage.getItem(key);
    } catch (e) {
        return null;
    }
};

const write = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch (e) {
        /* private mode: the choice simply does not persist */
    }
};

/* ---------------------------------------------------------------- Theme --- */
const applyTheme = (theme) => {
    const value = ["light", "dark", "system"].includes(theme) ? theme : "system";
    document.documentElement.dataset.theme = value;
    write(THEME_KEY, value);

    document.querySelectorAll("[data-theme-pick]").forEach((button) => {
        button.setAttribute("aria-pressed", button.dataset.themePick === value ? "true" : "false");
    });
    document.querySelectorAll('input[name="theme"]').forEach((radio) => {
        radio.checked = radio.value === value;
    });
};

const initTheme = () => {
    applyTheme(read(THEME_KEY) ?? document.documentElement.dataset.theme ?? "system");

    document.querySelectorAll("[data-theme-pick]").forEach((button) => {
        button.addEventListener("click", () => applyTheme(button.dataset.themePick));
    });
    document.querySelectorAll('input[name="theme"]').forEach((radio) => {
        radio.addEventListener("change", () => radio.checked && applyTheme(radio.value));
    });
};

/* ------------------------------------------------------------------ Menu --- */
const initMenus = () => {
    document.querySelectorAll("[data-menu-toggle]").forEach((button) => {
        const menu = document.getElementById(button.getAttribute("aria-controls"));

        if (!menu) {
            return;
        }

        const close = () => {
            menu.hidden = true;
            button.setAttribute("aria-expanded", "false");
        };

        button.addEventListener("click", () => {
            const open = menu.hidden;
            menu.hidden = !open;
            button.setAttribute("aria-expanded", open ? "true" : "false");
        });

        document.addEventListener("click", (event) => {
            if (!menu.hidden && !menu.contains(event.target) && !button.contains(event.target)) {
                close();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !menu.hidden) {
                close();
                button.focus();
            }
        });
    });
};

/* ----------------------------------------------------------------- Scene --- */
const initScene = () => {
    const scene = document.querySelector("[data-scene]");
    const button = document.querySelector("[data-scene-pause]");

    if (!scene || !button) {
        return;
    }

    const label = button.querySelector("[data-scene-label]");
    const icons = button.querySelectorAll("[data-scene-icon]");

    const apply = (paused) => {
        scene.classList.toggle("is-paused", paused);
        button.setAttribute("aria-pressed", paused ? "true" : "false");
        if (label) {
            label.textContent = paused ? button.dataset.playLabel : button.dataset.pauseLabel;
        }
        icons.forEach((icon) => {
            // inline SVG: toggle the attribute, HTMLElement.hidden does not exist on it
            icon.toggleAttribute("hidden", icon.dataset.sceneIcon !== (paused ? "play" : "pause"));
        });
        write(SCENE_KEY, paused ? "1" : "0");
    };

    apply(read(SCENE_KEY) === "1");
    button.addEventListener("click", () => apply(!scene.classList.contains("is-paused")));
};

/* ----------------------------------------------------------------- Clock --- */
const initClocks = () => {
    const clocks = document.querySelectorAll("[data-sf-time]");

    if (!clocks.length) {
        return;
    }

    const tick = () => {
        let time;

        try {
            time = new Intl.DateTimeFormat("en-US", {
                hour: "numeric",
                minute: "2-digit",
                timeZone: "America/Los_Angeles",
            }).format(new Date());
        } catch (e) {
            return;
        }

        clocks.forEach((clock) => {
            clock.textContent = time;
        });
    };

    tick();
    setInterval(tick, 15000);
};

/* ------------------------------------------------------------- Passwords --- */
const initPasswordToggles = () => {
    document.querySelectorAll("[data-pw-toggle]").forEach((button) => {
        const input = document.getElementById(button.getAttribute("aria-controls"));

        if (!input) {
            return;
        }

        button.addEventListener("click", () => {
            const show = input.type === "password";
            input.type = show ? "text" : "password";
            button.setAttribute("aria-pressed", show ? "true" : "false");
            button.textContent = show ? button.dataset.hideLabel : button.dataset.showLabel;
        });
    });
};

const RULES = {
    length: (value) => value.length >= 12,
    upper: (value) => /[A-Z]/.test(value),
    lower: (value) => /[a-z]/.test(value),
    number: (value) => /[0-9]/.test(value),
    symbol: (value) => /[^A-Za-z0-9]/.test(value),
};

const initPasswordStrength = () => {
    document.querySelectorAll("[data-pw-strength]").forEach((input) => {
        const root = input.closest("form") || document;
        const bars = root.querySelector("[data-strength-bars]");
        const label = root.querySelector("[data-strength-label]");
        const rules = root.querySelectorAll("[data-rule]");
        const confirm = root.querySelector("[data-pw-confirm]");
        const mismatch = root.querySelector("[data-pw-mismatch]");
        const words = bars ? JSON.parse(bars.dataset.words || "[]") : [];

        const update = () => {
            const value = input.value;
            let met = 0;

            rules.forEach((rule) => {
                const ok = (RULES[rule.dataset.rule] || (() => false))(value);
                met += ok ? 1 : 0;
                rule.classList.toggle("rule-ok", ok);
                rule.classList.toggle("rule-no", !ok);
                const status = rule.querySelector("[data-rule-status]");
                if (status) {
                    status.textContent = ok ? rule.dataset.doneText : rule.dataset.todoText;
                }
            });

            let score = met <= 2 ? 1 : met <= 4 ? 2 : value.length >= 16 ? 4 : 3;
            if (!value) {
                score = 0;
            }

            if (bars) {
                bars.dataset.score = String(score);
            }
            if (label && words[score] !== undefined) {
                label.textContent = words[score];
            }

            if (confirm && mismatch) {
                const differs = confirm.value.length > 0 && confirm.value !== value;
                mismatch.hidden = !differs;
                confirm.setAttribute("aria-invalid", differs ? "true" : "false");
            }
        };

        input.addEventListener("input", update);
        confirm?.addEventListener("input", update);
        update();
    });
};

/* -------------------------------------------------------------- Counters --- */
const initCounters = () => {
    document.querySelectorAll("[data-count-for]").forEach((counter) => {
        const input = document.getElementById(counter.dataset.countFor);

        if (!input) {
            return;
        }

        const update = () => {
            counter.textContent = `${input.value.length} / ${input.maxLength > 0 ? input.maxLength : ""}`.trim();
        };

        input.addEventListener("input", update);
        update();
    });
};

/* ---------------------------------------------------------- Reveal field --- */
const initReveals = () => {
    document.querySelectorAll("[data-reveal-on-change]").forEach((input) => {
        const target = document.getElementById(input.dataset.revealOnChange);

        if (!target) {
            return;
        }

        const initial = input.value;
        const update = () => {
            target.hidden = input.value === initial && !target.dataset.forceOpen;
        };

        input.addEventListener("input", update);
        update();
    });
};

/* ------------------------------------------------------------------ Copy --- */
const flash = (button, text) => {
    const original = button.dataset.originalLabel || button.textContent;
    button.dataset.originalLabel = original;
    button.textContent = text;
    setTimeout(() => {
        button.textContent = original;
    }, 1800);
};

const initCopy = () => {
    document.querySelectorAll("[data-copy]").forEach((button) => {
        button.addEventListener("click", async () => {
            const source = document.querySelector(button.dataset.copy);
            const text = source ? (source.dataset.copyValue || source.textContent).trim() : "";

            try {
                await navigator.clipboard.writeText(text);
                flash(button, button.dataset.copiedLabel || "Copied");
            } catch (e) {
                /* clipboard unavailable: leave the text selectable on the page */
            }
        });
    });

    document.querySelectorAll("[data-copy-list]").forEach((button) => {
        button.addEventListener("click", async () => {
            const items = [...document.querySelectorAll(button.dataset.copyList)].map((item) => item.textContent.trim());

            try {
                await navigator.clipboard.writeText(items.join("\n"));
                flash(button, button.dataset.copiedLabel || "Copied");
            } catch (e) {
                /* see above */
            }
        });
    });

    document.querySelectorAll("[data-download-list]").forEach((button) => {
        button.addEventListener("click", () => {
            const items = [...document.querySelectorAll(button.dataset.downloadList)].map((item) => item.textContent.trim());
            const blob = new Blob([items.join("\n") + "\n"], { type: "text/plain" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = button.dataset.filename || "recovery-codes.txt";
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    });
};

/* --------------------------------------------------- Filters and sorting --- */
const initFilters = () => {
    document.querySelectorAll("[data-filter-root]").forEach((root) => {
        const items = [...root.querySelectorAll("[data-filter-item]")];
        const search = root.querySelector("[data-filter-search]");
        const buttons = root.querySelectorAll("[data-filter]");
        const empty = root.querySelector("[data-filter-empty]");
        const emptyQuery = root.querySelector("[data-filter-query]");
        let filter = root.querySelector('[data-filter][aria-pressed="true"]')?.dataset.filter || "all";

        const apply = () => {
            const query = (search?.value || "").trim().toLowerCase();
            let shown = 0;

            items.forEach((item) => {
                const matchesFilter = filter === "all" || item.dataset.filterTags?.split(" ").includes(filter);
                const matchesQuery = !query || (item.dataset.filterText || "").toLowerCase().includes(query);
                const show = matchesFilter && matchesQuery;
                item.hidden = !show;
                shown += show ? 1 : 0;
            });

            if (empty) {
                empty.hidden = shown !== 0;
            }
            if (emptyQuery) {
                emptyQuery.textContent = query;
            }
        };

        search?.addEventListener("input", apply);
        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                filter = button.dataset.filter;
                buttons.forEach((other) => other.setAttribute("aria-pressed", other === button ? "true" : "false"));
                apply();
            });
        });
        apply();
    });

    document.querySelectorAll("[data-sort-root]").forEach((root) => {
        const body = root.querySelector("[data-sort-body]");
        const buttons = root.querySelectorAll("[data-sort]");
        const label = root.querySelector("[data-sort-label]");

        if (!body) {
            return;
        }

        const apply = (key) => {
            const rows = [...body.querySelectorAll("[data-sort-row]")];
            rows.sort((a, b) => Number(b.dataset[key] || 0) - Number(a.dataset[key] || 0) || Number(b.dataset.tiebreak || 0) - Number(a.dataset.tiebreak || 0));
            rows.forEach((row, index) => {
                body.appendChild(row);
                const rank = row.querySelector("[data-rank]");
                if (rank) {
                    rank.textContent = String(index + 1);
                    rank.classList.toggle("brand-ink", index === 0);
                    rank.classList.toggle("subtle", index !== 0);
                }
            });
        };

        buttons.forEach((button) => {
            button.addEventListener("click", () => {
                buttons.forEach((other) => other.setAttribute("aria-pressed", other === button ? "true" : "false"));
                if (label) {
                    label.textContent = button.dataset.sortLabel || button.textContent;
                }
                apply(button.dataset.sort);
            });
        });
    });
};

/* ----------------------------------------------------------- Leaderboard --- */
const initCategories = () => {
    const root = document.querySelector("[data-cats-root]");

    if (!root) {
        return;
    }

    const buttons = root.querySelectorAll("[data-cat]");
    const panels = root.querySelectorAll("[data-cat-panel]");

    const show = (id, push) => {
        let found = false;
        panels.forEach((panel) => {
            const match = panel.dataset.catPanel === id;
            panel.hidden = !match;
            found = found || match;
        });
        if (!found) {
            return;
        }
        buttons.forEach((button) => button.setAttribute("aria-pressed", button.dataset.cat === id ? "true" : "false"));
        if (push) {
            history.replaceState(null, "", `#${id}`);
        }
    };

    buttons.forEach((button) => button.addEventListener("click", () => show(button.dataset.cat, true)));

    const initial = location.hash.slice(1);
    show(initial && [...panels].some((panel) => panel.dataset.catPanel === initial) ? initial : panels[0]?.dataset.catPanel, false);
};

/* ---------------------------------------------------------------- Confirm --- */
const initConfirms = () => {
    document.querySelectorAll("form[data-confirm]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
};

document.addEventListener("DOMContentLoaded", () => {
    initTheme();
    initMenus();
    initScene();
    initClocks();
    initPasswordToggles();
    initPasswordStrength();
    initCounters();
    initReveals();
    initCopy();
    initFilters();
    initCategories();
    initConfirms();
});
