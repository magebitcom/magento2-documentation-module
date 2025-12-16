var config = {
    paths: {
        'hljs': 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min'
    },
    shim: {
        'hljs': {
            exports: 'hljs'
        }
    },
    map: {
        '*': {
            'documentationSearch': 'Magebit_Documentation/js/documentation-search',
            'documentationTree': 'Magebit_Documentation/js/documentation-tree'
        }
    }
};
