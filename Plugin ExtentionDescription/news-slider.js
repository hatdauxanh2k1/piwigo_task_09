$(document).ready(function () {
  const slider = $('#news-slider');
  // Hàm chuyển chuỗi thành mảng
  function parseAttribute(attributeValue, defaultValue) {
    // Kiểm tra nếu chuỗi rỗng hoặc null, trả về giá trị mặc định
    if (!attributeValue) return defaultValue;
    // Chuyển đổi chuỗi thành mảng số nguyên
    return attributeValue.split(',').map(Number);
  }
  // Lấy giá trị từ các thuộc tính HTML hoặc gán giá trị mặc định
  const itemsDesktop = parseAttribute(slider.attr('items-desktop'), [1199, 4]); // Mặc định [1199, 4]
  const itemsDesktopSmall = parseAttribute(
    slider.attr('items-desktop-small'),
    [980, 3]
  ); // Mặc định [980, 3]
  const itemsMobile = parseAttribute(slider.attr('items-mobile'), [600, 1]); // Mặc định [600, 1]
  const items = parseInt(slider.attr('items') || 4); // Mặc định 4
  // console.log('items: ', items);
  // console.log('itemsDesktop: ', itemsDesktop);
  // console.log('itemsDesktopSmall: ', itemsDesktopSmall);
  // console.log('itemsMobile: ', itemsMobile);
  $('#news-slider').owlCarousel({
    items: parseInt(items),
    itemsDesktop: itemsDesktop,
    itemsDesktopSmall: itemsDesktopSmall,
    itemsMobile: itemsMobile,
    navigation: true,
    navigationText: ['', ''],
    pagination: true,
    autoPlay: true,
  });
});
