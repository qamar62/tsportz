import Link from "next/link";
import Image from "next/image";

export function Brand({ light = false }: { light?: boolean }) {
  return <Link href="/" className={`brand ${light ? "brand-light" : ""}`} aria-label="Tabeer Sportz home">
    <span className="brand-logo-shell" aria-hidden="true"><Image src="/logo.png" alt="" width={45} height={45} className="brand-logo" /></span>
    <span className="brand-words"><strong>TABEER</strong><small>SPORTZ</small></span>
  </Link>;
}
