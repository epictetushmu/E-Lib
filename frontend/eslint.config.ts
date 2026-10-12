import { globalIgnores } from 'eslint/config'
import pluginVue from 'eslint-plugin-vue'
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript'

export default defineConfigWithVueTs(
  globalIgnores(['dist/**', 'dist-ssr/**', 'coverage/**', 'node_modules/**']),

  // 'essential' (error-prevention rules only) rather than 'recommended', which
  // also bundles Vue style-guide formatting rules (attribute wrapping,
  // self-closing tags, etc.) this codebase doesn't follow and has no
  // Prettier config to reconcile them with.
  pluginVue.configs['flat/essential'],
  vueTsConfigs.recommended,

  {
    rules: {
      // Route-level views are named after their route (Home.vue, Browse.vue,
      // Login.vue, Profile.vue, …), not a multi-word component name.
      'vue/multi-word-component-names': 'off',
    },
  },
)
