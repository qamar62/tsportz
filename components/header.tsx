"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { ArrowUpRight, Menu, Moon, Sun, X } from "lucide-react";
import { useEffect, useState } from "react";
import { Brand } from "./brand";

const links = [{ href: "/", label: "Home" }, { href: "/products", label: "Products" }, { href: "/about", label: "About" }, { href: "/contact", label: "Contact" }];

export function Header() {
  const [open, setOpen] = useState(false);
  const [dark, setDark] = useState(false);
  const pathname = usePathname();
  useEffect(() => setOpen(false), [pathname]);
  useEffect(() => setDark(document.documentElement.dataset.theme === "dark"), []);
  function toggleTheme() {
    const next = !dark;
    setDark(next);
    document.documentElement.dataset.theme = next ? "dark" : "light";
    try { localStorage.setItem("tabeer-theme", next ? "dark" : "light"); } catch { /* Browsers may disable storage. */ }
  }
  return <header className="site-header">
    <div className="header-inner container">
      <Brand />
      <nav className={`main-nav ${open ? "nav-open" : ""}`} aria-label="Main navigation">
        {links.map(link => <Link key={link.href} href={link.href} className={pathname === link.href ? "active" : ""}>{link.label}</Link>)}
        <a className="mobile-nav-cta" href="tel:+923353631555">Call +92 335 3631555 <ArrowUpRight size={17} /></a>
      </nav>
      <a href="/contact" className="header-cta">Start an inquiry <ArrowUpRight size={17} strokeWidth={2.2} /></a>
      <button className="theme-toggle" type="button" aria-label={dark ? "Switch to light mode" : "Switch to dark mode"} aria-pressed={dark} title={dark ? "Light mode" : "Dark mode"} onClick={toggleTheme}>{dark ? <Sun size={20} /> : <Moon size={20} />}</button>
      <button className="menu-toggle" type="button" aria-label={open ? "Close menu" : "Open menu"} aria-expanded={open} onClick={() => setOpen(!open)}>{open ? <X /> : <Menu />}</button>
    </div>
  </header>;
}
