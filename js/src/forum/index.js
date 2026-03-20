import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import Notification from 'flarum/forum/components/Notification';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Page from 'flarum/common/components/Page';

app.initializers.add('ralkage/civility-filter', () => {
  // Register notification type
  app.notificationComponents.civilityFlagged = CivilityFlaggedNotification;

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

class CivilityFlaggedNotification extends Notification {
  icon() {
    return 'fas fa-shield-alt';
  }

  href() {
    const notification = this.attrs.notification;
    const post = notification.subject();
    const discussion = post && post.discussion();

    if (discussion) {
      return app.route.discussion(discussion, post.number());
    }
    return '';
  }

  content() {
    const notification = this.attrs.notification;
    const data = notification.additionalData() || {};
    const action = data.action || 'warned';

    if (action === 'moderated') {
      return app.translator.trans('ralkage-civility-filter.forum.notification_moderated');
    }
    return app.translator.trans('ralkage-civility-filter.forum.notification_warned');
  }
}
