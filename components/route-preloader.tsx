"use client";

import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";

const MINIMUM_VISIBLE_MS = 260;
const SAFETY_TIMEOUT_MS = 4000;

export function RoutePreloader() {
  const pathname = usePathname();
  const [loading, setLoading] = useState(false);
  const startedAt = useRef(0);
  const navigationStarted = useRef(false);
  const finishTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const start = () => {
      if (finishTimer.current) clearTimeout(finishTimer.current);
      navigationStarted.current = true;
      startedAt.current = performance.now();
      setLoading(true);
    };

    const handleClick = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (!(event.target instanceof Element)) return;

      const link = event.target.closest("a[href]");
      if (!(link instanceof HTMLAnchorElement) || link.target === "_blank" || link.hasAttribute("download")) return;

      const destination = new URL(link.href, window.location.href);
      if (destination.origin !== window.location.origin) return;
      if (`${destination.pathname}${destination.search}` === `${window.location.pathname}${window.location.search}`) return;

      start();
    };

    document.addEventListener("click", handleClick, true);
    window.addEventListener("popstate", start);
    return () => {
      document.removeEventListener("click", handleClick, true);
      window.removeEventListener("popstate", start);
    };
  }, []);

  useEffect(() => {
    if (!navigationStarted.current) return;

    const elapsed = performance.now() - startedAt.current;
    const remaining = Math.max(0, MINIMUM_VISIBLE_MS - elapsed);
    finishTimer.current = setTimeout(() => {
      navigationStarted.current = false;
      setLoading(false);
    }, remaining);

    return () => {
      if (finishTimer.current) clearTimeout(finishTimer.current);
    };
  }, [pathname]);

  useEffect(() => {
    if (!loading) return;
    const safetyTimer = setTimeout(() => {
      navigationStarted.current = false;
      setLoading(false);
    }, SAFETY_TIMEOUT_MS);
    return () => clearTimeout(safetyTimer);
  }, [loading]);

  return (
    <div className={`route-preloader${loading ? " is-visible" : ""}`} role="status" aria-live="polite" aria-hidden={!loading}>
      <span className="route-preloader-line" />
      <span className="route-preloader-mark" aria-hidden="true">
        <span>TS</span>
      </span>
      <span className="route-preloader-label">Loading next play</span>
    </div>
  );
}
