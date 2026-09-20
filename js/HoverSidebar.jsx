import React, { useEffect, useRef, useState } from 'react';
import '../css/hover-sidebar.css';

const paths = {
  inventory: <><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 8h8M8 12h8M8 16h5" /></>,
  analytics: <path d="M3 3v18h18M8 16v-5M13 16V7M18 16v-8" />,
  settings: <><circle cx="12" cy="8" r="4" /><path d="M5 21v-2a7 7 0 0 1 14 0v2" /></>,
};
const defaultItems = [
  { href: '/analytics', label: 'Analytics', icon: 'analytics' },
  { id: 'office', label: 'Office Supplies', icon: 'inventory', children: [
    { href: '/inventory', label: 'Supply List', icon: 'inventory', stockCount: true },
    { href: '/ris', label: 'RIS', icon: 'inventory' },
    { href: '/rsmi', label: 'RSMI', icon: 'inventory' },
  ] },
  { href: '/settings', label: 'User Settings', icon: 'settings' },
];
function Icon({ name }) {
  return <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
    {paths[name] || paths.inventory}
  </svg>;
}

// Supply pathname from your router; pass Link as LinkComponent for SPA navigation.
export default function HoverSidebar({ pathname = '/analytics', items = defaultItems, LinkComponent = 'a', logoSrc = '/images/tesda_logo.png', outOfStockCount = 0 }) {
  const sidebarRef = useRef(null);
  const [isOpen, setIsOpen] = useState(() => {
    try { return sessionStorage.getItem('tesda:react-sidebar-open') === 'true'; } catch (_) { return false; }
  });
  const [activeCategory, setActiveCategory] = useState(() => {
    try {
      const saved = JSON.parse(sessionStorage.getItem('tesda:react-sidebar-menus'));
      return typeof saved === 'string' ? saved : (Array.isArray(saved) ? saved[0] ?? null : null);
    } catch (_) { return null; }
  });
  useEffect(() => {
    try { sessionStorage.setItem('tesda:react-sidebar-open', String(isOpen)); } catch (_) {}
  }, [isOpen]);
  useEffect(() => {
    try { sessionStorage.setItem('tesda:react-sidebar-menus', JSON.stringify(activeCategory)); } catch (_) {}
  }, [activeCategory]);
  useEffect(() => {
    const onOutsideClick = event => {
      if (sidebarRef.current && !sidebarRef.current.contains(event.target)) {
        setIsOpen(false);
        setActiveCategory(null);
      }
    };
    document.addEventListener('click', onOutsideClick);
    return () => document.removeEventListener('click', onOutsideClick);
  }, []);
  const renderLink = item => <LinkComponent key={item.href}
    {...(LinkComponent === 'a' ? { href: item.href } : { to: item.href })}
    onClick={() => setIsOpen(true)}
    className={pathname === item.href ? 'active' : undefined}
    aria-current={pathname === item.href ? 'page' : undefined}>
    <Icon name={item.icon} /><span>{item.label}</span>
    {item.stockCount && outOfStockCount > 0 && <span className="stock-count-badge" aria-label={`${outOfStockCount} items out of stock`}>{outOfStockCount}</span>}
  </LinkComponent>;
  return <aside ref={sidebarRef} className={`sidebar sidebar--hover${isOpen ? ' is-open' : ''}`} aria-label="Sidebar navigation"
    onClick={event => event.stopPropagation()}>
    <div className="logo"><img src={logoSrc} alt="TESDA logo" /><h3>TESDA Inventory</h3></div>
    <nav aria-label="Main navigation">
      {items.map(item => {
        if (!item.children?.length) return renderLink(item);
        const key = item.id || item.label;
        const expanded = isOpen && activeCategory === key;
        return <div key={key} className={`dropdown${expanded ? ' open' : ''}`}>
          <button type="button" className="dropdown-toggle" aria-expanded={expanded}
            onClick={() => {
              setIsOpen(true);
              setActiveCategory(current => current === key ? null : key);
            }}>
            <Icon name={item.icon} /><span>{item.label}</span>
            {item.children.some(child => child.stockCount) && outOfStockCount > 0 &&
              <span className="stock-count-badge stock-warning-badge" aria-label="Office supplies out of stock">!</span>}
            <span className="nav-chevron" aria-hidden="true"><svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75"><path d="m9 5 7 7-7 7" /></svg></span>
          </button>
          <div className="dropdown-menu"><div className="submenu-content">{item.children.map(renderLink)}</div></div>
        </div>;
      })}
    </nav>
  </aside>;
}
