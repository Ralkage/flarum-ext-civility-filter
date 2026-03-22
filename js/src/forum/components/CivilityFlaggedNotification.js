import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

export default class CivilityFlaggedNotification extends Notification {
  icon() {
    return 'fas fa-shield-alt';
  }

  href() {
    const notification = this.attrs.notification;
    const discussion = notification.subject();
    const data = notification.content() || {};

    if (discussion) {
      return app.route.discussion(discussion, data.postNumber);
    }
    return '';
  }

  content() {
    const notification = this.attrs.notification;
    const data = notification.content() || {};
    const action = data.action || 'warned';

    if (action === 'moderated') {
      return app.translator.trans('ralkage-civility-filter.forum.notification_moderated');
    }
    return app.translator.trans('ralkage-civility-filter.forum.notification_warned');
  }

  excerpt() {
    const notification = this.attrs.notification;
    const data = notification.content() || {};
    return data.reason || '';
  }
}
