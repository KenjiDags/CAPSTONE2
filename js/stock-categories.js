/* Shared Office Supplies categories used by inventory and analytics. */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.StockCategories = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    const categoryMap = {
        'office-supplies': ['fastener', 'paper', 'bond', 'notebook', 'pad', 'ream', 'envelope', 'pen', 'ballpen', 'gel pen', 'marker', 'highlighter', 'ink', 'folder', 'file', 'filing', 'index tab', 'document holder', 'staples', 'paper clip', 'clip', 'binder clip', 'tape', 'glue', 'adhesive', 'paste', 'pin', 'note', 'book'],
        'office-equipment': ['ruler', 'chair', 'table', 'desk', 'cabinet', 'printer', 'scanner', 'projector', 'laminator', 'shredder', 'furniture', 'cutter', 'stapler', 'staplers'],
        'cleaning-supplies': ['conditioner', 'dishwashing', 'detergent', 'soap', 'bleach', 'mop', 'broom', 'brush', 'disinfectant', 'cleaner', 'trash bag', 'garbage', 'alcohol', 'tissue', 'towel', 'rags', 'sponge'],
        'electronics': ['laptop', 'computer', 'monitor', 'keyboard', 'mouse', 'ups', 'router','calculator', 'switch', 'tablet', 'phone', 'speaker', 'camera', 'headset', 'microphone', 'usb', 'cable', 'charger', 'adapter', 'electronic', 'battery', 'led', 'bulb'],
        'miscelaneous': ['misc', 'miscellaneous', 'assorted', 'other', 'general', 'various', 'accessory', 'supply']
    };

    const categoryExclusions = {
        'office-equipment': ['pen', 'ballpen', 'paper', 'notebook', 'folder', 'ink', 'marker', 'highlighter']
    };

    const primaryCategories = ['office-supplies', 'office-equipment', 'cleaning-supplies', 'electronics'];


    function matchesPrimary(rowText, category) {
        if (rowText.includes('cutter paper')) {
            return category === 'office-equipment';
        }

        if (rowText.includes('kitchen towel')) {
            return category === 'cleaning-supplies';
        }

        if (rowText.includes('tape dispenser')) {
            return category === 'office-equipment';
        }

        const keywords = categoryMap[category] || [];
        let isMatch = keywords.some(function(keyword) {
            return rowText.includes(keyword);
        });

        const exclusions = categoryExclusions[category] || [];
        if (isMatch && exclusions.some(function(term) { return rowText.includes(term); })) {
            isMatch = false;
        }

        if (category === 'office-supplies' && (rowText.includes('tissue paper') || rowText.includes('scouring pad') || rowText.includes('stapler'))) {
            isMatch = false;
        }

        return isMatch;
    }


    const categories = [
      { id: 'office-supplies', label: 'Office Supplies' },
      { id: 'office-equipment', label: 'Office Equipment' },
      { id: 'cleaning-supplies', label: 'Cleaning Supplies' },
      { id: 'electronics', label: 'Electronics' },
      { id: 'miscelaneous', label: 'Miscellaneous' }
    ];
    function matches(text, category = 'all') {
      const rowText = String(text).toLowerCase();
      if (category === 'all') return true;
      return category === 'miscelaneous'
        ? !primaryCategories.some(primary => matchesPrimary(rowText, primary))
        : matchesPrimary(rowText, category);
    }
    // Assign each stock record once so category totals reconcile with inventory.
    function summarizeHealth(items) {
      const summary = categories.map(category => ({ ...category, total: 0, safe: 0, reorder: 0, empty: 0 }));
      items.forEach(item => {
        const quantity = Number(item.onHandQty ?? item.quantity);
        const threshold = Math.max(0, Number(item.safetyStock ?? item.reorder_point ?? 0));
        if (!Number.isFinite(quantity) || !Number.isFinite(threshold)) return;
        const text = (item.itemName ?? item.item_name ?? '') + ' ' + (item.description ?? '');
        const category = summary.find(entry => matches(text, entry.id)) || summary[summary.length - 1];
        category.total++;
        category[quantity <= 0 ? 'empty' : quantity > threshold ? 'safe' : 'reorder']++;
      });
      return summary;
    }
    return { categories, matches, summarizeHealth };
});
