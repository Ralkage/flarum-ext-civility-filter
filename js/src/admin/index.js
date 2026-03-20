import app from 'flarum/admin/app';

export { default as extend } from './extend';

app.initializers.add('ralkage/civility-filter', () => {
  // Runtime initialization handled by extend.js extenders
});
