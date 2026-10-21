
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("outfit-form");
    if (!form) return;

    const separatesPicker = document.getElementById("separates-picker");
    const dressPicker = document.getElementById("dress-picker");

    const modeInputs = document.querySelectorAll('input[name="outfit_type"]');

    const topItem = document.getElementById("top-preview-item");
    const bottomItem = document.getElementById("bottom-preview-item");
    const dressItem = document.getElementById("dress-preview-item");

    function updateItem(inputName, imageId, placeholderId, nameId) {
        const selected = form.querySelector(
            'input[name="' + inputName + '"]:checked'
        );

        const image = document.getElementById(imageId);
        const placeholder = document.getElementById(placeholderId);
        const nameLabel = document.getElementById(nameId);

        if (!selected) {
            image.hidden = true;
            image.removeAttribute("src");
            placeholder.hidden = false;
            nameLabel.textContent = "";
            return;
        }

        image.src = selected.dataset.image;
        image.alt = selected.dataset.name;
        image.hidden = false;
        placeholder.hidden = true;
        nameLabel.textContent = selected.dataset.name;
    }

    function updateMode() {
        const mode = form.querySelector(
            'input[name="outfit_type"]:checked'
        ).value;

        const isDress = mode === "dress";

        separatesPicker.hidden = isDress;
        dressPicker.hidden = !isDress;

        topItem.hidden = isDress;
        bottomItem.hidden = isDress;
        dressItem.hidden = !isDress;

        form.querySelectorAll('input[name="top_id"]').forEach(input => {
            input.required = !isDress;
        });

        form.querySelectorAll('input[name="bottom_id"]').forEach(input => {
            input.required = !isDress;
        });

        form.querySelectorAll('input[name="dress_id"]').forEach(input => {
            input.required = isDress;
        });

        updatePreview();
    }

    function updatePreview() {
        updateItem("top_id", "top-preview", "top-placeholder", "top-preview-name");
        updateItem("bottom_id", "bottom-preview", "bottom-placeholder", "bottom-preview-name");
        updateItem("dress_id", "dress-preview", "dress-placeholder", "dress-preview-name");
    }

    form.querySelectorAll('.visual-clothing-card input[type="radio"]').forEach(input => {
        input.addEventListener("change", function () {
            const group = input.name;

            form.querySelectorAll(
                '.visual-clothing-card input[name="' + group + '"]'
            ).forEach(radio => {
                radio.closest(".visual-clothing-card").classList.toggle(
                    "selected",
                    radio.checked
                );
            });

            updatePreview();
        });
    });

    modeInputs.forEach(input => {
        input.addEventListener("change", updateMode);
    });

    form.addEventListener("submit", function (event) {
        const mode = form.querySelector(
            'input[name="outfit_type"]:checked'
        ).value;

        const valid = mode === "dress"
            ? form.querySelector('input[name="dress_id"]:checked')
            : form.querySelector('input[name="top_id"]:checked') &&
              form.querySelector('input[name="bottom_id"]:checked');

        if (!valid) {
            event.preventDefault();
            alert(
                mode === "dress"
                    ? "Please select a dress photo first."
                    : "Please select both a top and a bottom photo."
            );
        }
    });

    function syncSelectedCards() {
        form.querySelectorAll('.visual-clothing-card input[type="radio"]').forEach(input => {
            input.closest(".visual-clothing-card").classList.toggle(
                "selected",
                input.checked
            );
        });
    }

    syncSelectedCards();
    updateMode();
});
