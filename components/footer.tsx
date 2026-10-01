import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { Brand } from "./brand";

export function Footer() {
  return <footer className="footer">
    <div className="container footer-main">
      <div className="footer-intro"><Brand light /><p>Made for the teams that show up, stand together and go further.</p></div>
      <div className="footer-column"><span className="footer-label">EXPLORE</span><Link href="/">Home</Link><Link href="/products">Products</Link><Link href="/about">About us</Link><Link href="/contact">Contact</Link></div>
      <div className="footer-column"><span className="footer-label">LET'S TALK</span><a href="tel:+923353631555">+92 335 3631555</a><a href="https://wa.me/923353631555" target="_blank" rel="noopener noreferrer">WhatsApp <ArrowUpRight size={15} /></a></div>
    </div>
    <div className="container footer-bottom"><span>© {new Date().getFullYear()} TABEER SPORTZ. All rights reserved.</span><span>Built for every game.</span></div>
  </footer>;
}
