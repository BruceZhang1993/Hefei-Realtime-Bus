const path = require('path');
let prod = process.env.NODE_ENV === 'production';

module.exports = {
  eslint: true,
  wpyExt: ".wpy",
  build: {
    web: {
      apis: ['showToast', 'showActionSheet', 'showModal'],
      components: ['navigator', 'button', 'icon', 'progress', 'slider', 'radio', 'radio-group', 'checkbox', 'checkbox-group', 'switch'],
      htmlTemplate: path.join('src', 'index.template.html'),
      htmlOutput: path.join('web', 'index.html'),
      jsOutput: path.join('web', 'index.js')
    }
  },
  compilers: {
    babel: {
      sourceMap: true,
      presets: [
        "es2015",
        "stage-1"
      ],
      plugins: [
        "transform-export-extensions",
        "syntax-export-extensions",
      ]
    }
  },
  plugins: {
    'px2units': {
      filter: /\.wxss$/
    }
  }
};

if (prod) {

  delete module.exports.compilers.babel.sourcesMap;

  // 压缩less
  module.exports.compilers['less'] = {compress: true}

  // 压缩js
  module.exports.plugins = {
    'px2units': {
        filter: /\.wxss$/
    },
    uglifyjs: {
        filter: /\.js$/,
        config: {
        }
    },
    'filemin': {
        filter: /\.(json|wxml|xml)$/
    },
    'imagemin': {
        filter: /\.(jpg|png|jpeg)$/,
        config: {
            'jpg': {
                quality: 80
            },
            'png': {
                quality: 80
            }
        }
    },
  }
}

