import Link from "next/link";
import { ArrowUpRight } from "lucide-react";

export default function NotFound() { return <section className="not-found container"><span className="eyebrow">404 / PAGE NOT FOUND</span><h1>OFF THE<br /><em>FIELD.</em></h1><p>Looks like this page is out of play.</p><Link href="/" className="btn btn-teal">Back to home <ArrowUpRight size={18} /></Link></section>; }
