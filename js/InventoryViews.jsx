import React, { useState } from 'react';

// Optional React equivalent. The live PHP app opens add_multiple_items.php.
// Supply the actual view components and their data from the parent application.
export default function InventoryViews({ AnalyticsView, RestockInventoryView, analyticsProps = {}, restockProps = {} }) {
  const [activeView, setActiveView] = useState('analytics');

  return (
    <main className="main-container">
      {activeView === 'analytics' ? (
        <AnalyticsView
          {...analyticsProps}
          onRestock={() => setActiveView('restock')}
        />
      ) : (
        <RestockInventoryView
          {...restockProps}
          onBack={() => setActiveView('analytics')}
        />
      )}
    </main>
  );
}
