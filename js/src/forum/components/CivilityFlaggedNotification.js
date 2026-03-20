import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class CivilityFlaggedNotification extends Notification {
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
