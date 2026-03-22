import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';

export { default as extend } from './extend';

app.initializers.add('ralkage/civility-filter', () => {
  // Add civility notice badges to posts
  extend(CommentPost.prototype, 'headerItems', function (items) {
    const post = this.attrs.post;
    const civilityAction = post.attribute('civilityAction');

    if (!civilityAction || civilityAction === '' || civilityAction === 'allowed') return;

    const user = app.session.user;
    if (!user) return;

    const isAuthor = post.user() && post.user().id() === user.id();
    const isMod = user.attribute('isAdmin');
    if (!isAuthor && !isMod) return;

    let message = '';
    let badgeClass = '';

    if (civilityAction === 'warned') {
      badgeClass = 'CivilityNotice--warned';
      message = isAuthor
        ? app.translator.trans('ralkage-civility-filter.forum.post_notice_warned_author')
        : app.translator.trans('ralkage-civility-filter.forum.post_notice_warned_mod');
    } else if (civilityAction === 'moderated') {
      badgeClass = 'CivilityNotice--moderated';
      message = app.translator.trans('ralkage-civility-filter.forum.post_notice_moderated');
    }

    if (message) {
      items.add('civility-notice', <div className={`CivilityNotice ${badgeClass}`}><i className="fas fa-shield-alt" /> {message}</div>, -100);
    }
  });
});
