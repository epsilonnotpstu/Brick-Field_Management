// Add to cart function
async function addToCart(productId) {
    try {
        const response = await fetch('api/cart/add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: 1
            }),
            credentials: 'include' // For session cookies
        });
        
        const result = await response.json();
        
        if (response.ok) {
            alert('Product added to cart!');
            updateCartCount();
        } else {
            alert(result.message || 'Failed to add to cart');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error adding to cart');
    }
}

// Update cart count in navbar
async function updateCartCount() {
    try {
        const response = await fetch('api/cart/count.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        const cartCountElement = document.getElementById('cart-count');
        if (cartCountElement) {
            cartCountElement.textContent = data.count || 0;
        }
    } catch (error) {
        console.error('Error updating cart count:', error);
    }
}

// Load cart items
async function loadCart() {
    try {
        const response = await fetch('api/cart/get.php', {
            credentials: 'include'
        });
        const data = await response.json();
        
        const cartItemsContainer = document.getElementById('cart-items');
        const totalAmountElement = document.getElementById('total-amount');
        
        if (!data.items || data.items.length === 0) {
            cartItemsContainer.innerHTML = '<p>Your cart is empty</p>';
            totalAmountElement.textContent = '0';
            return;
        }
        
        let html = '';
        let total = 0;
        
        data.items.forEach(item => {
            const itemTotal = item.base_price * item.quantity;
            total += itemTotal;
            
            html += `
                <div class="cart-item">
                    <img src="${item.image_url}" alt="${item.display_name}" width="100">
                    <div>
                        <h3>${item.display_name}</h3>
                        <p>Price: ৳${item.base_price}</p>
                        <div class="quantity-control">
                            <button onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})">-</button>
                            <span>${item.quantity}</span>
                            <button onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})">+</button>
                        </div>
                        <p>Total: ৳${itemTotal.toFixed(2)}</p>
                        <button onclick="removeFromCart(${item.product_id})" class="btn-remove">Remove</button>
                    </div>
                </div>
            `;
        });
        
        cartItemsContainer.innerHTML = html;
        totalAmountElement.textContent = total.toFixed(2);
        
    } catch (error) {
        console.error('Error loading cart:', error);
        cartItemsContainer.innerHTML = '<p>Error loading cart items</p>';
    }
}

// Update quantity function
async function updateQuantity(productId, newQuantity) {
    if (newQuantity < 1) {
        removeFromCart(productId);
        return;
    }
    
    try {
        const response = await fetch('api/cart/update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: newQuantity
            }),
            credentials: 'include'
        });
        
        if (response.ok) {
            loadCart();
        }
    } catch (error) {
        console.error('Error updating quantity:', error);
    }
}

// Remove from cart function
async function removeFromCart(productId) {
    try {
        const response = await fetch('api/cart/remove.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            }),
            credentials: 'include'
        });
        
        if (response.ok) {
            loadCart();
            updateCartCount();
        }
    } catch (error) {
        console.error('Error removing item:', error);
    }
}

// Initialize cart when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('cart-items')) {
        loadCart();
    }
    updateCartCount();
});