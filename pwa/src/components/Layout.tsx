import { Outlet } from 'react-router';
import { Footer } from './Footer';
import { Header } from './Header';
import { UpdateNotice } from './UpdateNotice';

/** Shared frame of all pages: blue header, notice about a new version, content, footer. */
export function Layout() {
  return (
    <div className="layout">
      <Header />
      <UpdateNotice />
      <div className="layout-content"><Outlet /></div>
      <Footer />
    </div>
  );
}
