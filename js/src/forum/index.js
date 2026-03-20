import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';

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

  // Add civility history link to user profile sidebar (admin only)
  extend(UserPage.prototype, 'navItems', function (items) {
    if (app.session.user && app.session.user.attribute('isAdmin')) {
      const user = this.user;
      if (user) {
        items.add('civility',
          <LinkButton href={app.route('user.civility', { username: user.slug() })} icon="fas fa-shield-alt">
            {app.translator.trans('ralkage-civility-filter.forum.user_profile.civility_title')}
          </LinkButton>,
          -100
        );
      }
    }
  });
});
