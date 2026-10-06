// === Фотогалерея ===
// Положи фото в папку images/photos/ и допиши имена сюда:
const photos = [
    "photo1.jpg",
    "photo2.jpg",
    "photo3.jpg"
    // "photo4.jpg", "photo5.jpg" и т.д.
  ];
  
  const gallery = document.getElementById("photoGallery");
  const photoPath = "images/photos/";
  
  if (gallery) {
    photos.forEach(function(filename) {
      const div = document.createElement("div");
      div.className = "gallery-item";
      div.innerHTML =
        '<a href="' + photoPath + filename + '" data-fancybox="gallery">' +
        '<img src="' + photoPath + filename + '" alt="Фото компании" loading="lazy">' +
        '</a>';
      gallery.appendChild(div);
    });
  }
  