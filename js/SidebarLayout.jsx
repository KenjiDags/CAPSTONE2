import React from 'react';
import HoverSidebar from './HoverSidebar.jsx';

// Pass pathname from the router and Link as LinkComponent for SPA navigation.
// The sidebar owns its width; flexbox gives main the remaining space.
export default function SidebarLayout({ children, pathname, LinkComponent, items }) {
  return (
    <div className="app-layout">
      <HoverSidebar pathname={pathname} LinkComponent={LinkComponent} items={items} />
      <main className="app-main">{children}</main>
    </div>
  );
}
