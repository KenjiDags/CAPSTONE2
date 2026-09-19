import React from 'react';
import '../css/hover-sidebar.css';

const paths = {
  inventory: <><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h5" /></>,
  analytics: <path d="M3 3v18h18M8 16v-5M13 16V7M18 16v-8" />,
  settings: <><circle cx="12" cy="8" r="4" /><path d="M5 21v-2a7 7 0 0 1 14 0v2" /></>,
};
const defaultItems = [
  { href: '/analytics', label: 'Analytics', icon: 'analytics' },
  { href: '/inventory', label: 'Office Supplies', icon: 'inventory' },
  { href: '/settings', label: 'User Settings', icon: 'settings' },
];
function Icon({ name }) {
  return <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
    {paths[name] || paths.inventory}
  </svg>;
}

// Supply pathname from your router; pass Link as LinkComponent for SPA navigation.
export default function HoverSidebar({ pathname = '/analytics', items = defaultItems, LinkComponent = 'a', logoSrc = '/images/tesda_logo.png' }) {
  return <aside className="sidebar sidebar--hover" aria-label="Sidebar navigation">
    <div className="logo"><img src={logoSrc} alt="TESDA logo" /><h3>TESDA Inventory</h3></div>
    <nav aria-label="Main navigation">
      {items.map(item => <LinkComponent key={item.href}
        {...(LinkComponent === 'a' ? { href: item.href } : { to: item.href })}
        className={pathname === item.href ? 'active' : undefined}
        aria-current={pathname === item.href ? 'page' : undefined}>
        <Icon name={item.icon} /><span>{item.label}</span>
      </LinkComponent>)}
    </nav>
  </aside>;
}
