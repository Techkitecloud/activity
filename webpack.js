const path = require('path')

const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	sidebar: path.join(__dirname, 'src', 'sidebar.js'),
	dashboard: path.join(__dirname, 'src', 'dashboard.js'),
}

module.exports = webpackConfig
