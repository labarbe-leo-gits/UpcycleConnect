document.addEventListener('DOMContentLoaded', function () {
		const modal = document.getElementById('easterEggModal');
		const closeBtn = modal.querySelector('.egg-close');

		function openModal()  { modal.classList.add('is-open'); }
		function closeModal() { modal.classList.remove('is-open'); }

		closeBtn.addEventListener('click', closeModal);

		modal.addEventListener('click', function (event) {
			if (event.target === modal) closeModal();
		});

		document.querySelector('.by-petisign-badge').addEventListener('click', openModal);
	});