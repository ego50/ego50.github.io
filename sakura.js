(function () {
  var conteneur = document.querySelector(".sakura-container");
  if (!conteneur) return;
  var NOMBRE = 35;
  for (var i = 0; i < NOMBRE; i++) {
    var petal = document.createElement("span");
    petal.className = "sakura-petal";
    var taille = Math.random() * 7 + 7;
    petal.style.left = Math.random() * 100 + "%";
    petal.style.width = taille + "px";
    petal.style.height = taille * 0.65 + "px";
    petal.style.opacity = Math.random() * 0.45 + 0.35;
    petal.style.animationDuration = (Math.random() * 12 + 10) + "s, " + (Math.random() * 3 + 2) + "s";
    petal.style.animationDelay = (Math.random() * -20) + "s, " + (Math.random() * -5) + "s";
    conteneur.appendChild(petal);
  }
})();
