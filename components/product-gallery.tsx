"use client";

import Image from "next/image";
import { useState } from "react";
import type { ProductImage } from "@/lib/products";

type ProductGalleryProps = {
  image?: ProductImage;
  gallery?: ProductImage[];
  icon: string;
  number: string;
  category: string;
  tone: number;
};

export function ProductGallery({ image, gallery = [], icon, number, category, tone }: ProductGalleryProps) {
  const images = [image, ...gallery]
    .filter((item): item is ProductImage => Boolean(item))
    .filter((item, index, items) => items.findIndex(candidate => candidate.url === item.url) === index);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const selected = images[selectedIndex] || images[0];

  return (
    <div className="product-gallery">
      <div className={`detail-art product-tone-${tone}${selected ? " has-photo" : ""}`}>
        {selected ? (
          <Image
            key={selected.url}
            src={selected.url}
            alt={selected.alt}
            fill
            sizes="(max-width: 650px) 100vw, 50vw"
            className="detail-photo"
            priority
          />
        ) : (
          <span className="detail-symbol" aria-hidden="true">{icon}</span>
        )}
        <span className="detail-art-number">{number} / 13</span>
        <span className="detail-art-label">TABEER SPORTZ / {category.toUpperCase()}</span>
      </div>

      {images.length > 1 && (
        <div className="detail-thumbnails" aria-label="Product gallery">
          {images.map((galleryImage, index) => (
            <button
              className={index === selectedIndex ? "selected" : ""}
              key={`${galleryImage.url}-${index}`}
              type="button"
              onClick={() => setSelectedIndex(index)}
              aria-label={`View product image ${index + 1} of ${images.length}`}
              aria-pressed={index === selectedIndex}
            >
              <Image
                src={galleryImage.url}
                alt=""
                fill
                sizes="96px"
                className="detail-thumbnail-image"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
