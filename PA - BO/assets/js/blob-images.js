function loadBlobImages() {
    var imgs = document.querySelectorAll('img[data-blob-src]');
    
    imgs.forEach(function(img) {
        var src = img.getAttribute('data-blob-src');
        if (src) {
            fetch(src)
                .then(function(response) {
                    return response.blob();
                })
                .then(function(blob) {
                    var blobUrl = URL.createObjectURL(blob);
                    img.src = blobUrl;
                })
                .catch(function(error) {
                    console.error('Failed to load blob image:', src, error);
                    img.src = src;
                });
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadBlobImages);
} else {
    loadBlobImages();
}

var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    var newImgs = node.querySelectorAll ? node.querySelectorAll('img[data-blob-src]') : [];
                    if (node.tagName === 'IMG' && node.hasAttribute('data-blob-src')) {
                        newImgs = [node];
                    }
                    newImgs.forEach(function(img) {
                        if (!img.src || img.src === '') {
                            var src = img.getAttribute('data-blob-src');
                            if (src) {
                                fetch(src)
                                    .then(function(response) {
                                        return response.blob();
                                    })
                                    .then(function(blob) {
                                        var blobUrl = URL.createObjectURL(blob);
                                        img.src = blobUrl;
                                    })
                                    .catch(function(error) {
                                        console.error('Failed to load blob image:', src, error);
                                        img.src = src;
                                    });
                            }
                        }
                    });
                }
            });
        }
    });
});

function attachObserver() {
    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    } else {
        document.addEventListener('DOMContentLoaded', attachObserver);
    }
}

attachObserver();
