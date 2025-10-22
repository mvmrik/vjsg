<template>
  <div>
    <c-card>
      <c-card-header>
        <strong>{{ $t('help.title') }}</strong>
      </c-card-header>
      <c-card-body>
        <div class="mb-3">
          <p v-html="$t('help.intro_html')"></p>
        </div>

        <div class="mb-4">
          <h5>{{ $t('help.contents_title') }}</h5>
          <ul>
            <li v-for="item in toc" :key="item.anchor">
              <a :href="'#' + item.anchor">{{ $t(item.titleKey) }}</a>
            </li>
          </ul>
        </div>

        <div v-for="section in sections" :key="section.anchor" class="mb-5">
          <h4 :id="section.anchor">{{ $t(section.titleKey) }}</h4>
          <div v-html="$t(section.bodyKey)"></div>
        </div>
      </c-card-body>
    </c-card>
  </div>
</template>

<script setup>
import { inject, reactive } from 'vue';

const $t = inject('$t');

const toc = reactive([
  { anchor: 'getting-started', titleKey: 'help.toc.getting_started' },
  { anchor: 'game-basics', titleKey: 'help.toc.game_basics' },
  { anchor: 'resources', titleKey: 'help.toc.resources' },
  { anchor: 'parcels', titleKey: 'help.toc.parcels' },
  { anchor: 'construction', titleKey: 'help.toc.construction' },
  { anchor: 'economy', titleKey: 'help.toc.economy' },
  { anchor: 'accounts-security', titleKey: 'help.toc.accounts_security' },
]);

const sections = reactive([
  {
    anchor: 'getting-started',
    titleKey: 'help.sections.getting_started.title',
    bodyKey: 'help.sections.getting_started.body',
  },
  {
    anchor: 'game-basics',
    titleKey: 'help.sections.game_basics.title',
    bodyKey: 'help.sections.game_basics.body',
  },
  {
    anchor: 'resources',
    titleKey: 'help.sections.resources.title',
    bodyKey: 'help.sections.resources.body',
  },
  {
    anchor: 'parcels',
    titleKey: 'help.sections.parcels.title',
    bodyKey: 'help.sections.parcels.body',
  },
  {
    anchor: 'construction',
    titleKey: 'help.sections.construction.title',
    bodyKey: 'help.sections.construction.body',
  },
  {
    anchor: 'economy',
    titleKey: 'help.sections.economy.title',
    bodyKey: 'help.sections.economy.body',
  },
  {
    anchor: 'accounts-security',
    titleKey: 'help.sections.accounts_security.title',
    bodyKey: 'help.sections.accounts_security.body',
  },
]);
</script>

<style scoped>
.toc a { text-decoration: none; }
</style>
