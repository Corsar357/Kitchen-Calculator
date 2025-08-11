// ▼▼▼ ЗМІНА 1: Імпортуємо функцію перекладу ▼▼▼
const { __ } = wp.i18n;

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.kc-calculator-block').forEach(block => {
    const defIng  = block.getAttribute('data-default-ingredient');
    const defFrom = block.getAttribute('data-default-from');
    const defTo   = block.getAttribute('data-default-to');
    const precision = parseInt(block.getAttribute('data-precision') || '2', 10);

    const ingSelect  = block.querySelector('.kc-ingredient');
    const amountInput= block.querySelector('.kc-amount');
    const fromSelect = block.querySelector('.kc-from-unit');
    const toSelect   = block.querySelector('.kc-to-unit');
    const resultEl   = block.querySelector('.kc-result');
    const tabs       = block.querySelector('.kc-tabs');
    if (tabs) { tabs.setAttribute('role','tablist'); }
    const swapBtn    = block.querySelector('.kc-swap');

    function formatNumber(n){ try{ return Number(n).toLocaleString(undefined,{maximumFractionDigits:precision}); }catch(e){ return Number(n).toFixed(precision); } }

    function calc() {
      const payload = {
        ingredient: ingSelect.value,
        from: fromSelect.value,
        to: toSelect.value,
        amount: parseFloat(amountInput.value || '0')
      };
      if (!payload.amount) { resultEl.textContent = ''; return; }
      
      wp.apiFetch({ path: '/kc/v1/convert', method:'POST', data: payload })
        .then(res => {
          if (res && res.result !== undefined) {
            resultEl.textContent = formatNumber(res.result) + ' ' + (toSelect.options[toSelect.selectedIndex]?.text || '');
          } else { resultEl.textContent = ''; }
        })
        .catch(() => { resultEl.textContent = '—'; });
            
      // ▼▼▼ Рецепти ▼▼▼
      const recipeLinksEl = block.querySelector('.kc-recipe-links');
      if (recipeLinksEl) {
        const recipePath = `/kc/v1/recipes?ingredient=${encodeURIComponent(payload.ingredient)}`;
        wp.apiFetch({ path: recipePath })
            .then(recipes => {
                if (recipes && recipes.length > 0) {
                    // ▼▼▼ ЗМІНА 2: Локалізуємо заголовок рецептів ▼▼▼
                    let html = '<h4>' + __('Recipes with this ingredient:', 'kitchen-calculator') + '</h4><ul>';
                    recipes.forEach(recipe => {
                        html += `<li><a href="${recipe.link}">${recipe.title}</a></li>`;
                    });
                    html += '</ul>';
                    recipeLinksEl.innerHTML = html;
                } else {
                    recipeLinksEl.innerHTML = '';
                }
            })
            .catch(() => {
                recipeLinksEl.innerHTML = '';
            });
      }
    } // кінець функції calc

    function loadIngredients(catSlug) {
      const path = catSlug && catSlug!=='all' ? `/kc/v1/ingredients?category=${encodeURIComponent(catSlug)}` : '/kc/v1/ingredients';
      return wp.apiFetch({ path }).then(ings => {
        ingSelect.innerHTML = ings.map(ing => `<option value="${ing.slug}">${ing.name}</option>`).join('');
        if (defIng) ingSelect.value = defIng;
      });
    }

    // init: load categories and units
    Promise.all([
      wp.apiFetch({ path: '/kc/v1/categories' }),
      wp.apiFetch({ path: '/kc/v1/units' })
    ]).then(([categories, units]) => {
      // tabs
      let html = categories.map(c => `<button type=\"button\" class=\"kc-tab\" data-slug=\"${c.slug}\">${c.name}</button>`).join('');
      // ▼▼▼ ЗМІНА 3: Локалізуємо назву вкладки "Всі" ▼▼▼
      html += `<button type=\"button\" class=\"kc-tab is-active\" data-slug=\"all\">${__('All ingredients', 'kitchen-calculator')}</button>`;
      tabs.innerHTML = html;

      tabs.addEventListener('click', (e) => {
        const btn = e.target.closest('.kc-tab'); if(!btn) return;
        tabs.querySelectorAll('.kc-tab').forEach(b => b.classList.toggle('is-active', b===btn));
        loadIngredients(btn.dataset.slug).then(calc);
      });

      // units
      fromSelect.innerHTML = units.map(u => `<option value="${u.slug}">${u.label}</option>`).join('');
      toSelect.innerHTML   = units.map(u => `<option value="${u.slug}">${u.label}</option>`).join('');
      if (defFrom) fromSelect.value = defFrom;
      if (defTo)   toSelect.value = defTo;

      // first load ingredients
      loadIngredients('all').then(calc);
    });

    // events
    ['change','input'].forEach(evt => {
      ingSelect.addEventListener(evt, calc);
      amountInput.addEventListener(evt, calc);
      fromSelect.addEventListener(evt, calc);
      toSelect.addEventListener(evt, calc);
    });

    if (swapBtn) {
      swapBtn.addEventListener('click', () => {
        const tmp = fromSelect.value;
        fromSelect.value = toSelect.value;
        toSelect.value = tmp;
        calc();
      });
    }
  });
});