// === Фотогалерея ===
// Положи фото в папку images/photos/ и допиши имена сюда:
// const photos = [
//     "photo1.jpg",
//     "photo2.jpg",
//     "photo3.jpg",
//     "photo4.jpg",
//     "photo5.jpg",
//     "photo6.jpg",
//     "photo7.jpg"
//   ];
  
  const gallery = document.getElementById("photoGallery");
  const photoPath = "images/photos/";
  const totalPhotos = 8; // Укажи, сколько всего фото в папке

  const photos = [];
  for (let i = 1; i <= totalPhotos; i++) {
    photos.push(`photo${i}.jpg`);
  }


  if (gallery && Array.isArray(photos)) {
    // 1. Создаём ОДИН общий контейнер-обёртку с нужным классом
    const container = document.createElement("div");
    container.className = "photos_block";

    photos.forEach(function(filename) {
      const a = document.createElement("a");
      a.href = photoPath + filename;
      a.dataset.fancybox = "gallery";

      const img = document.createElement("img");
      img.src = photoPath + filename;
      img.alt = "Фото компании";
      img.loading = "lazy";

      a.appendChild(img);
      
      // 2. Добавляем каждую ссылку ВНУТРЬ общего контейнера
      container.appendChild(a);
    });

    // 3. Вставляем готовый контейнер в галерею
    gallery.appendChild(container);
}


  